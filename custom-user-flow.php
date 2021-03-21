<?php
/**
 * Plugin Name: Custom-User-Flow
 * Plugin URI: http://www.modexy987.com/custom-user-flow
 * Description: The very first plugin that I have ever created.
 * Version: 1.0
 * Author: Mourad EL CADI
 * Author URI: http://www.modexy987.com
 */


class Custom_User_Flow_Plugin
{
    private static $domain = 'Custom_User_Flow';

    // Information needed for creating the plugin's pages
    private static function get_custom_pages()
    {
        return array(
            'member-login' => array(
                'title' => __( 'Sign In', 'Custom_User_Flow'),
                'content' => '[custom-login-form]'
            ),

            'member-account' => array(
                'title' => __( 'Your Account', 'Custom_User_Flow'),
                'content' => '[account-info]'
            ),

            'member-register' => array(
                'title' => __( 'Register', 'Custom_User_Flow'),
                'content' => '[custom-register-form]'
            ),
            'member-password-lost' => array(
                'title' => __( 'Pick a New Password', 'Custom_User_Flow'),
                'content' => '[custom-password-lost-form]'
            ),
            'member-password-reset' => array(
                'title' => __( 'Reset Your Password', 'Custom_User_Flow'),
                'content' => '[custom-password-reset-form]'
            )
        );
    }

    /**
     * Initializes the plugin.
     *
     * To keep the initialization fast, only add 'filter' and 'action'
     * hooks in the constructor.
     */
    public function __construct()
    {
        add_shortcode('custom-login-form', array($this, 'render_login_form'));
        add_shortcode( 'custom-register-form', array( $this, 'render_register_form' ) );
        add_shortcode( 'custom-password-lost-form', array( $this, 'render_password_lost_form' ) );
        add_shortcode( 'custom-password-reset-form', array( $this, 'render_password_reset_form' ) );

        add_action( 'login_form_login', array( $this, 'redirect_to_custom_login'));
        add_action( 'login_form_register', array( $this, 'redirect_to_custom_register' ) );
        add_action( 'login_form_register', array( $this, 'do_register_user' ) );
        add_action( 'login_form_lostpassword', array( $this, 'redirect_to_custom_lostpassword' ) );
        add_action( 'login_form_lostpassword', array( $this, 'do_password_lost' ) );
        add_action( 'login_form_rp', array( $this, 'redirect_to_custom_password_reset' ) );
        add_action( 'login_form_resetpass', array( $this, 'redirect_to_custom_password_reset' ) );
        add_action( 'login_form_rp', array( $this, 'do_password_reset' ) );
        add_action( 'login_form_resetpass', array( $this, 'do_password_reset' ) );

        add_action( 'wp_logout', array( $this, 'redirect_after_logout' ) );
        add_action( 'wp_print_footer_scripts', array( $this, 'add_captcha_js_to_footer' ) );

        add_filter( 'authenticate', array( $this, 'maybe_redirect_at_authentication_error' ), 101, 3 );
        add_filter( 'login_redirect', array( $this, 'redirect_after_login' ), 10, 3 );
        add_filter( 'admin_init' , array( $this, 'register_settings_fields' ) );
        add_filter( 'retrieve_password_message', array( $this, 'replace_retrieve_password_message' ), 10, 4 );
    }


    /**
     * Plugin activation hook.
     *
     * Creates all WordPress pages needed by the plugin.
     */
    public static function plugin_activated()
    {
        foreach (self::get_custom_pages() as $slug => $page ) {
            // Check that the page doesn't exist already
            $query = new WP_Query( 'pagename=' . $slug );
            if ( ! $query->have_posts() ) {
                // Add the page using the data from the array above
                wp_insert_post(array(
                    'post_title'     => $page['title'],
                    'post_content'   => $page['content'],
                    'post_name'      => $slug,
                    'post_status'    => 'publish',
                    'post_type'      => 'page',
                    'ping_status'    => 'closed',
                    'comment_status' => 'closed',
                ));
            }
        }

        flush_rewrite_rules();
    }


    /**
     * Renders the contents of the given template to a string and returns it.
     *
     * @param string $template The name of the template to render (without .php)
     * @param array  $attributes    The PHP variables for the template
     *
     * @return string               The contents of the template.
     */
    private function get_template_html($template, $attributes = array())
    {
        ob_start();

        do_action( 'custom_' . $template . '_before_' . $template );

        require( 'templates/member-' . $template . '.php');

        do_action( 'custom_' . $template . '_after_' . $template );

        $html = ob_get_contents();
        ob_end_clean();
    
        return $html;
    }


    /**
     * A shortcode for rendering the login form.
     *
     * @param  array   $attributes  Shortcode attributes.
     * @param  string  $content     The text content for shortcode. Not used.
     *
     * @return string  The shortcode output
     */
    public function render_login_form( $attributes, $content = null )
    {
        if (is_user_logged_in()) {
            return __( 'You are already signed in.', self::$domain);
        }

        // Parse shortcode attributes
        $default_attributes = array( 'show_title' => false );
        $attributes = shortcode_atts( $default_attributes, $attributes, 'custom-login-form');
        $show_title = $attributes['show_title'];

        
        // Pass the redirect parameter to the WordPress login functionality: by default,
        // don't specify a redirect, but if a valid redirect URL has been passed as
        // request parameter, use it.
        $attributes['redirect'] = '';
        if ( isset( $_REQUEST['redirect_to'] ) ) {
            $attributes['redirect'] = wp_validate_redirect( $_REQUEST['redirect_to'], $attributes['redirect'] );
        }
        

        // Error messages
        $errors = array();
        if ( isset( $_REQUEST['login'] ) ) {
            $error_codes = explode( ',', $_REQUEST['login'] );
        
            foreach ( $error_codes as $code ) {
                $errors []= $this->get_error_message( $code );
            }
        }


        // Check if user just logged out
        $attributes['logged_out'] = isset( $_REQUEST['logged_out'] ) && filter_var($_REQUEST['logged_out'], 258) == true;

        $attributes['errors'] = $errors;
        // Render the login form using an external template

        // Check if the user just requested a new password
        $attributes['lost_password_sent'] = isset( $_REQUEST['checkemail'] ) && $_REQUEST['checkemail'] == 'confirm';

        // Check if user just updated password
        $attributes['password_updated'] = isset( $_REQUEST['password'] ) && $_REQUEST['password'] == 'changed';

        return $this->get_template_html( 'login', $attributes );
    }


    /**
     * A shortcode for rendering the new user registration form.
     *
     * @param  array   $attributes  Shortcode attributes.
     * @param  string  $content     The text content for shortcode. Not used.
     *
     * @return string  The shortcode output
     */
    public function render_register_form( $attributes, $content = null )
    {
        // Parse shortcode attributes
        $default_attributes = array( 'show_title' => false );
        $attributes = shortcode_atts( $default_attributes, $attributes, 'custom-register-form');
    
        if ( is_user_logged_in() ) {
            return __( 'You are already signed in.', self::$domain);
        } elseif ( ! get_option( 'users_can_register' ) ) {
            return __( 'Registering new users is currently not allowed.', self::$domain);
        } else {

            // Retrieve possible errors from request parameters
            $attributes['errors'] = array();
            if ( isset( $_REQUEST['register-errors'] ) ) {
                $error_codes = explode(',', $_REQUEST['register-errors'] );
            
                foreach ( $error_codes as $error_code ) {
                    $attributes['errors'] []= $this->get_error_message( $error_code );
                }
            }

            // Check if the user just registered
            $attributes['registered'] = isset( $_REQUEST['registered'] );

            // Retrieve recaptcha key
            $attributes['recaptcha_site_key'] = get_option( 'personalize-login-recaptcha-site-key', null );

            return $this->get_template_html( 'register', $attributes );
        }
    }


    /**
     * A shortcode for rendering the form used to initiate the password reset.
     *
     * @param  array   $attributes  Shortcode attributes.
     * @param  string  $content     The text content for shortcode. Not used.
     *
     * @return string  The shortcode output
     */
    public function render_password_lost_form( $attributes, $content = null )
    {
        // Parse shortcode attributes
        $default_attributes = array( 'show_title' => false );
        $attributes = shortcode_atts( $default_attributes, $attributes, 'custom-password-lost-form' );
    
        if ( is_user_logged_in() ) {
            return __( 'You are already signed in.', self::$domain);
        } else {

            // Retrieve possible errors from request parameters
            $attributes['errors'] = array();
            if ( isset( $_REQUEST['errors'] ) ) {
                $error_codes = explode( ',', $_REQUEST['errors'] );
            
                foreach ( $error_codes as $error_code ) {
                    $attributes['errors'] []= $this->get_error_message( $error_code );
                }
            }

            return $this->get_template_html( 'password_lost', $attributes );
        }
    }



    /**
     * A shortcode for rendering the form used to reset a user's password.
     *
     * @param  array   $attributes  Shortcode attributes.
     * @param  string  $content     The text content for shortcode. Not used.
     *
     * @return string  The shortcode output
     */
    public function render_password_reset_form( $attributes, $content = null )
    {
        // Parse shortcode attributes
        $default_attributes = array( 'show_title' => false );
        $attributes = shortcode_atts( $default_attributes, $attributes );
    
        if ( is_user_logged_in() ) {
            return __( 'You are already signed in.', self::$domain);
        } else {
            if ( isset( $_REQUEST['login'] ) && isset( $_REQUEST['key'] ) ) {
                $attributes['login'] = $_REQUEST['login'];
                $attributes['key'] = $_REQUEST['key'];
    
                // Error messages
                $errors = array();
                if ( isset( $_REQUEST['error'] ) ) {
                    $error_codes = explode( ',', $_REQUEST['error'] );
    
                    foreach ( $error_codes as $code ) {
                        $errors []= $this->get_error_message( $code );
                    }
                }
                $attributes['errors'] = $errors;
    
                return $this->get_template_html( 'password_reset', $attributes );
            } else {
                return __( 'Invalid password reset link.', self::$domain);
            }
        }
    }



    /**
     * Finds and returns a matching error message for the given error code.
     *
     * @param string $error_code    The error code to look up.
     *
     * @return string               An error message.
     */
    private function get_error_message( $error_code )
    {
        switch ( $error_code ) {
            case 'empty_username':
                return __( 'You do have an email address, right?', self::$domain );
    
            case 'empty_password':
                return __( 'You need to enter a password to login.', self::$domain );
    
            case 'invalid_username':
                return __(
                    "We don't have any users with that email address. Maybe you used a different one when signing up?",
                    self::$domain
                );
    
            case 'incorrect_password':
                $err = __(
                    "The password you entered wasn't quite right. <a href='%s'>Did you forget your password</a>?",
                    self::$domain
                );
                return sprintf( $err, wp_lostpassword_url() );
            
            // Registration errors
            case 'email':
                return __( 'The email address you entered is not valid.', self::$domain);
            
            case 'email_exists':
                return __( 'An account exists with this email address.', self::$domain);
            
            case 'closed':
                return __( 'Registering new users is currently not allowed.', self::$domain);
            
            case 'captcha':
                return __( 'The Google reCAPTCHA check failed. Are you a robot?', self::$domain);

            // Lost password
            case 'empty_username':
                return __( 'You need to enter your email address to continue.', self::$domain);
            
            case 'invalid_email':
            case 'invalidcombo':
                return __( 'There are no users registered with this email address.', self::$domain);

            case 'expiredkey':
            case 'invalidkey':
                return __( 'The password reset link you used is not valid anymore.', self::$domain);
            
            case 'password_reset_mismatch':
                return __( "The two passwords you entered don't match.", self::$domain);
                
            case 'password_reset_empty':
                return __( "Sorry, we don't accept empty passwords.", self::$domain);
    
            default:
                break;
        }
        
        return __( 'An unknown error occurred. Please try again later.', self::$domain );
    }



    /**
    * Redirects the user to the correct page depending on whether he / she
    * is an admin or not.
    *
    * @param string $redirect_to   An optional redirect_to URL for admin users
    */
    private function redirect_logged_in_user( $redirect_to = null )
    {
        $user = wp_get_current_user();
        if ( user_can( $user, 'manage_options' ) ) {
            if ( $redirect_to ) {
                wp_safe_redirect( $redirect_to );
            } else {
                wp_redirect( admin_url() );
            }
        } else {
            wp_redirect( home_url( 'member-account' ) );
        }
    }


    /**
    * Redirect the user to the custom login page instead of wp-login.php.
    */
    function redirect_to_custom_login()
    {
        if ( $_SERVER['REQUEST_METHOD'] == 'GET' ) {
            $redirect_to = isset( $_REQUEST['redirect_to'] ) ? $_REQUEST['redirect_to'] : null;

            if ( is_user_logged_in() ) {
                $this->redirect_logged_in_user( $redirect_to );
                exit;
            }

            // The rest are redirected to the login page
            $login_url = home_url( 'member-login' );
            if ( ! empty( $redirect_to ) ) {
                $login_url = add_query_arg( 'redirect_to', $redirect_to, $login_url );
            }

            wp_redirect( $login_url );
            exit;
        }
    }



    /**
     * Redirect the user after authentication if there were any errors.
     *
     * @param Wp_User|Wp_Error  $user       The signed in user, or the errors that have occurred during login.
     * @param string            $username   The user name used to log in.
     * @param string            $password   The password used to log in.
     *
     * @return Wp_User|Wp_Error The logged in user, or error information if there were errors.
     */
    function maybe_redirect_at_authentication_error( $user, $username, $password )
    {
        // Check if the earlier authenticate filter (most likely, 
        // the default WordPress authentication) functions have found errors
        if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
            if ( is_wp_error( $user ) ) {
                $error_codes = join( ',', $user->get_error_codes() );
    
                $login_url = home_url( 'member-login' );
                $login_url = add_query_arg( 'login', $error_codes, $login_url );
    
                wp_redirect( $login_url );
                exit;
            }
        }
    
        return $user;
    }


    /**
    * Redirect to custom login page after the user has been logged out.
    */
    public function redirect_after_logout()
    {
        $redirect_url = home_url( 'member-login?logged_out=true' );
        wp_safe_redirect( $redirect_url );
        exit;
    }

    /**
    * Returns the URL to which the user should be redirected after the (successful) login.
    *
    * @param string $redirect_to The redirect destination URL.
    * @param string $requested_redirect_to The requested redirect destination URL passed as a parameter.
    * @param WP_User|WP_Error $user WP_User object if login was successful, WP_Error object otherwise.
    *
    * @return string Redirect URL
    */
    public function redirect_after_login( $redirect_to, $requested_redirect_to, $user )
    {
        $redirect_url = home_url();
    
        if (!isset( $user->ID )) {
            return $redirect_url;
        }
    
        if ( user_can( $user, 'manage_options' ) ) {
            // Use the redirect_to parameter if one is set, otherwise redirect to admin dashboard.
            if ( $requested_redirect_to == '' ) {
                $redirect_url = admin_url();
            } else {
                $redirect_url = $requested_redirect_to;
            }
        } else {
            // Non-admin users always go to their account page after login
            $redirect_url = home_url( 'member-account' );
        }
    
        return wp_validate_redirect( $redirect_url, home_url() );
    }


    /**
     *  Redirects the user to the custom registration page instead
     *  of wp-login.php?action=register.
     */
    public function redirect_to_custom_register() {
        if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
            if ( is_user_logged_in() ) {
                $this->redirect_logged_in_user();
            } else {
                wp_redirect( home_url( 'member-register' ) );
            }
            exit;
        }
    }



    /**
     * Validates and then completes the new user signup process if all went well.
     *
     * @param string $email         The new user's email address
     * @param string $first_name    The new user's first name
     * @param string $last_name     The new user's last name
     *
     * @return int|WP_Error         The id of the user that was created, or error if failed.
     */
    private function register_user( $email, $first_name, $last_name )
    {
        $errors = new WP_Error();
    
        // Email address is used as both username and email. It is also the only
        // parameter we need to validate
        if ( ! is_email( $email ) ) {
            $errors->add( 'email', $this->get_error_message( 'email' ) );
            return $errors;
        }
    
        if ( username_exists( $email ) || email_exists( $email ) ) {
            $errors->add( 'email_exists', $this->get_error_message( 'email_exists') );
            return $errors;
        }
    
        // Generate the password so that the subscriber will have to check email...
        $password = wp_generate_password( 12, false );
    
        $user_id = wp_insert_user(array(
            'user_login'    => $email,
            'user_email'    => $email,
            'user_pass'     => $password,
            'first_name'    => $first_name,
            'last_name'     => $last_name,
            'nickname'      => $first_name,
        ));

        wp_new_user_notification( $user_id, $password ); # it use last_inserted_id ...
    
        return $user_id;
    }


    /**
    * Handles the registration of a new user.
    *
    * Used through the action hook "login_form_register" activated on wp-login.php
    * when accessed through the registration action.
    */
    public function do_register_user() {
        if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
            $redirect_url = home_url( 'member-register' );
    
            if ( ! get_option( 'users_can_register' ) ) {
                // Registration closed, display error
                $redirect_url = add_query_arg( 'register-errors', 'closed', $redirect_url );
            } elseif ( ! $this->verify_recaptcha() ) {
                // Recaptcha check failed, display error
                $redirect_url = add_query_arg( 'register-errors', 'captcha', $redirect_url );
            } else {
                $email = $_POST['email'];
                $first_name = sanitize_text_field( $_POST['first_name'] );
                $last_name = sanitize_text_field( $_POST['last_name'] );
    
                $result = $this->register_user( $email, $first_name, $last_name );
    
                if ( is_wp_error( $result ) ) {
                    // Parse errors into a string and append as parameter to redirect
                    $errors = join( ',', $result->get_error_codes() );
                    $redirect_url = add_query_arg( 'register-errors', $errors, $redirect_url );
                } else {
                    // Success, redirect to login page.
                    $redirect_url = home_url( 'member-login' );
                    $redirect_url = add_query_arg( 'registered', $email, $redirect_url );
                }
            }
    
            wp_redirect( $redirect_url );
            exit;
        }
    }



    /**
    * Registers the settings fields needed by the plugin.
    */
    public function register_settings_fields()
    {
        // Create settings fields for the two keys used by reCAPTCHA
        register_setting( 'general', 'personalize-login-recaptcha-site-key' );
        register_setting( 'general', 'personalize-login-recaptcha-secret-key' );
    
        add_settings_field(
            'personalize-login-recaptcha-site-key',
            '<label for="personalize-login-recaptcha-site-key">' . __( 'reCAPTCHA site key' , self::$domain ) . '</label>',
            array( $this, 'render_recaptcha_site_key_field' ),
            'general'
        );
    
        add_settings_field(
            'personalize-login-recaptcha-secret-key',
            '<label for="personalize-login-recaptcha-secret-key">' . __( 'reCAPTCHA secret key' , self::$domain ) . '</label>',
            array( $this, 'render_recaptcha_secret_key_field' ),
            'general'
        );
    }
    
    public function render_recaptcha_site_key_field() {
        $value = get_option( 'personalize-login-recaptcha-site-key', '' );
        echo '<input type="text" id="personalize-login-recaptcha-site-key" name="personalize-login-recaptcha-site-key" value="' . esc_attr( $value ) . '" />';
    }
    
    public function render_recaptcha_secret_key_field() {
        $value = get_option( 'personalize-login-recaptcha-secret-key', '' );
        echo '<input type="text" id="personalize-login-recaptcha-secret-key" name="personalize-login-recaptcha-secret-key" value="' . esc_attr( $value ) . '" />';
    }

    /**
     * An action function used to include the reCAPTCHA JavaScript file
     * at the end of the page.
     */
    public function add_captcha_js_to_footer()
    {
        echo "<script src='https://www.google.com/recaptcha/api.js'></script>";
    }


    /**
     * Checks that the reCAPTCHA parameter sent with the registration
     * request is valid.
     *
     * @return bool True if the CAPTCHA is OK, otherwise false.
     */
    private function verify_recaptcha()
    {
        // This field is set by the recaptcha widget if check is successful
        if ( isset ( $_POST['g-recaptcha-response'] ) ) {
            $captcha_response = $_POST['g-recaptcha-response'];
        } else {
            return false;
        }
    
        // Verify the captcha response from Google
        $response = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            array(
                'body' => array(
                    'secret' => get_option( 'personalize-login-recaptcha-secret-key' ),
                    'response' => $captcha_response
                )
            )
        );
    
        $success = false;
        if ( $response && is_array( $response ) ) {
            $decoded_response = json_decode( $response['body'] );
            $success = $decoded_response->success;
        }
    
        return $success;
    }



    /**
     * Redirects the user to the custom "Forgot your password?" page instead of
     * wp-login.php?action=lostpassword.
     */
    public function redirect_to_custom_lostpassword()
    {
        if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
            if ( is_user_logged_in() ) {
                $this->redirect_logged_in_user();
                exit;
            }
    
            wp_redirect( home_url( 'member-password-lost' ) );
            exit;
        }
    }



    /**
     * Initiates password reset.
     * 
     */
    public function do_password_lost()
    {
        if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
            $errors = retrieve_password();
            if ( is_wp_error( $errors ) ) {
                // Errors found
                $redirect_url = home_url( 'member-password-lost' );
                $redirect_url = add_query_arg( 'errors', join( ',', $errors->get_error_codes() ), $redirect_url );
            } else {
                // Email sent
                $redirect_url = home_url( 'member-login' );
                $redirect_url = add_query_arg( 'checkemail', 'confirm', $redirect_url );
            }
    
            wp_redirect( $redirect_url );
            exit;
        }
    }


    /**
     * Returns the message body for the password reset mail.
     * Called through the retrieve_password_message filter.
     *
     * @param string  $message    Default mail message.
     * @param string  $key        The activation key.
     * @param string  $user_login The username for the user.
     * @param WP_User $user_data  WP_User object.
     *
     * @return string   The mail message to send.
     */
    public function replace_retrieve_password_message( $message, $key, $user_login, $user )
    {
        // Create new message
        $message  = __( 'Hello!', self::$domain) . "\r\n\r\n";
        $message .= sprintf( __( 'You asked us to reset your password for your account using the email address %s.', self::$domain), $user->user_email ) . "\r\n\r\n";
        $message .= __( "If this was a mistake, or you didn't ask for a password reset, just ignore this email and nothing will happen.", self::$domain) . "\r\n\r\n";
        $message .= __( 'To reset your password, visit the following address:', self::$domain) . "\r\n\r\n";
        $message .= site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . "\r\n\r\n";
        $message .= __( 'Thanks!', self::$domain) . "\r\n";
    
        return $message;
    }



    /**
     * Redirects to the custom password reset page, or the login page
     * if there are errors.
     */
    public function redirect_to_custom_password_reset()
    {
        if ( 'GET' == $_SERVER['REQUEST_METHOD'] ) {
            // Verify key / login combo
            $user = check_password_reset_key( $_REQUEST['key'], $_REQUEST['login'] );
            if ( ! $user || is_wp_error( $user ) ) {
                if ( $user && $user->get_error_code() === 'expired_key' ) {
                    wp_redirect( home_url( 'member-login?login=expiredkey' ) );
                } else {
                    wp_redirect( home_url( 'member-login?login=invalidkey' ) );
                }
                exit;
            }
    
            $redirect_url = home_url( 'member-password-reset' );
            $redirect_url = add_query_arg( 'login', esc_attr( $_REQUEST['login'] ), $redirect_url );
            $redirect_url = add_query_arg( 'key', esc_attr( $_REQUEST['key'] ), $redirect_url );
    
            wp_redirect( $redirect_url );
            exit;
        }
    }


    /**
     * Resets the user's password if the password reset form was submitted.
     */
    public function do_password_reset()
    {
        if ( 'POST' == $_SERVER['REQUEST_METHOD'] ) {
            $rp_key = $_REQUEST['rp_key'];
            $rp_login = $_REQUEST['rp_login'];
    
            $user = check_password_reset_key( $rp_key, $rp_login );
    
            if ( ! $user || is_wp_error( $user ) ) {
                if ( $user && $user->get_error_code() === 'expired_key' ) {
                    wp_redirect( home_url( 'member-login?login=expiredkey' ) );
                } else {
                    wp_redirect( home_url( 'member-login?login=invalidkey' ) );
                }
                exit;
            }
    
            if ( isset( $_POST['pass1'] ) ) {
                if ( $_POST['pass1'] != $_POST['pass2'] ) {
                    // Passwords don't match
                    $redirect_url = home_url( 'member-password-reset' );
    
                    $redirect_url = add_query_arg( 'key', $rp_key, $redirect_url );
                    $redirect_url = add_query_arg( 'login', $rp_login, $redirect_url );
                    $redirect_url = add_query_arg( 'error', 'password_reset_mismatch', $redirect_url );
    
                    wp_redirect( $redirect_url );
                    exit;
                }
    
                if ( empty( $_POST['pass1'] ) ) {
                    // Password is empty
                    $redirect_url = home_url( 'member-password-reset' );
    
                    $redirect_url = add_query_arg( 'key', $rp_key, $redirect_url );
                    $redirect_url = add_query_arg( 'login', $rp_login, $redirect_url );
                    $redirect_url = add_query_arg( 'error', 'password_reset_empty', $redirect_url );
    
                    wp_redirect( $redirect_url );
                    exit;
                }
    
                // Parameter checks OK, reset password
                reset_password( $user, $_POST['pass1'] );
                wp_redirect( home_url( 'member-login?password=changed' ) );
            } else {
                echo "Invalid request.";
            }

            exit;
        }
    }
    
    /**
    * Deactivation hook to unregister our existing Contacts Role
    */
    public function plugin_deactivation() {
        
        wp_delete_post();
        
        foreach (self::get_custom_pages() as $slug => $page ) {

            // $args = array(
            //     'post_type'      => 'page',
            //     'posts_per_page' => 1,
            //     'post_name'      => $slug,
            //     'fields'         => 'ids' 
            // );
            // $post = get_posts( $args );      # this returns an array so use '$post[0]'

            $post = get_page_by_path($slug);

            if ( !empty($post)) {
                wp_delete_post($post->ID, true);
            }
        }

        flush_rewrite_rules();
    }
}
 

// Initialize the plugin
$custom_user_flow = new Custom_User_Flow_Plugin();

register_activation_hook(__FILE__, array('Custom_User_Flow_Plugin', 'plugin_activated'));
register_deactivation_hook( __FILE__, array( 'Custom_User_Flow_Plugin', 'plugin_deactivation' ) );

add_filter( 'wp_nav_menu_objects', function( $items, $args) {

    // if ( 'primary' !== $args->theme_location ) {
    //     return $items;
    // }

    return array_filter( $items, function( $item ) {
        if(home_url('member-password-reset/') == $item->url){
            return false;
        }
        
        if(home_url('member-password-lost/') == $item->url){
            return false;
        }

        $urls = array(
            home_url('member-login/'),
            home_url('member-register/'),
        );

        if(in_array($item->url, $urls)){
            return !is_user_logged_in();
        }

        return true;
    });

}, 10, 2 );