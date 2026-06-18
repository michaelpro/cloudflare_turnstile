<?php

class cloudflare_turnstile extends rcube_plugin
{
    public $task = 'login|logout';

    public function init()
    {
        $this->add_hook('template_object_loginform', [$this, 'add_turnstile_widget']);
        $this->add_hook('authenticate', [$this, 'authenticate']);
    }

    public function add_turnstile_widget($p)
    {
        $rcmail = rcmail::get_instance();

        if ($this->is_bypassed()) {
            return $p;
        }

        $sitekey = $rcmail->config->get('turnstile_sitekey');
        $theme   = $rcmail->config->get('turnstile_theme', 'light');

        if (!empty($sitekey)) {
            // Load Turnstile script
            $rcmail->output->add_header(html::tag('script', [
                'src'   => 'https://challenges.cloudflare.com/turnstile/v0/api.js',
                'async' => true,
                'defer' => true,
            ]));

            // Add a clear label above the widget
            $p['content'] .= html::div(['class' => 'form-group', 'style' => 'margin: 10px 0 5px 0;'],
                html::tag('label', [
                    'style' => 'display:block; margin-bottom: 6px; font-size: 0.9em; color: #444; font-weight: 500;'
                ], 'Please verify you are human')
            );

            // Add Turnstile widget
            $p['content'] .= html::div(['class' => 'form-group', 'style' => 'margin: 0 0 15px 0;'],
                html::tag('div', [
                    'class'        => 'cf-turnstile',
                    'data-sitekey' => $sitekey,
                    'data-theme'   => $theme,
                    'data-callback' => 'onTurnstileSuccess',
                    'data-expired-callback' => 'onTurnstileExpired',
                ])
            );

            // JavaScript to disable/enable login button
            $script = <<<JS
            document.addEventListener('DOMContentLoaded', function() {
                var loginBtn = document.querySelector('button[type="submit"], input[type="submit"]');
                if (loginBtn) {
                    loginBtn.disabled = true;
                }

                window.onTurnstileSuccess = function() {
                    if (loginBtn) loginBtn.disabled = false;
                };

                window.onTurnstileExpired = function() {
                    if (loginBtn) loginBtn.disabled = true;
                };
            });
            JS;

            $rcmail->output->add_script($script, 'docready');
        }

        // === SSL.COM Site Seal ===
        if ($rcmail->config->get('sslcom_seal_enabled')) {
            $seal_html = $this->get_sslcom_seal_html();
            if ($seal_html) {
                $rcmail->output->add_footer($seal_html);
            }
        }

        return $p;
    }

    public function authenticate($p)
    {
        $rcmail = rcmail::get_instance();

        if ($this->is_bypassed()) {
            return $p;
        }

        $secret = $rcmail->config->get('turnstile_secret');
        $token  = rcube_utils::get_input_string('cf-turnstile-response', rcube_utils::INPUT_POST);

        if (empty($secret)) {
            return $p;
        }

        if (empty($token)) {
            $p['abort'] = true;
            $p['error'] = 'loginfailed';
            $rcmail->output->show_message('loginfailed', 'error');
            return $p;
        }

        $response = $this->verify_turnstile($secret, $token);

        if (empty($response['success'])) {
            $p['abort'] = true;
            $p['error'] = 'loginfailed';
            $rcmail->output->show_message('loginfailed', 'error');
            rcube::write_log('userlogins', 'Turnstile validation failed');
        }

        return $p;
    }

    private function verify_turnstile($secret, $token)
    {
        $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

        $data = [
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 8,
            ],
        ];

        $context = stream_context_create($options);
        $result  = @file_get_contents($url, false, $context);

        return json_decode($result, true) ?: ['success' => false];
    }

    private function get_sslcom_seal_html()
    {
        $rcmail = rcmail::get_instance();

        $team = $rcmail->config->get('sslcom_seal_team', 'ab0-1jk9cpq');
        $seal = $rcmail->config->get('sslcom_seal_id', 'd0c9eb94-6744b803');

        return html::div([
            'id'    => 'login-addon',
            'style' => 'background:#769294; border:12px solid #17a2b8; padding:0.5em; margin:4em auto; width:30em; text-align:center;'
        ],
            html::tag('h3', ['style' => 'margin-top:0.2em; font-weight:bold;'], 'Site Secured By SSL.COM') .
            html::p(null,
                '<a style="border:none; display:inline-block;" ' .
                'onclick="window.open(\'https://secure.ssl.com/team/' . $team . '/site_seals/' . $seal . '/site_report\', \'site_report\', \'height=500,width=500,top=75,left=75\'); return false;" ' .
                'onmouseover="this.style.cursor=\'pointer\'" ' .
                'href="https://secure.ssl.com/team/' . $team . '/site_seals/' . $seal . '/site_report">' .
                '<img width="130px" src="https://d2ria90rzqh48t.cloudfront.net/assets/ssl_seal_1_ev-247ab60ddea5f9a52469fa057e038e73df1d620b140d10f648117c6cb8940a44.png" alt="SSL Secured" />' .
                '</a>'
            )
        );
    }

    private function is_bypassed()
    {
        $rcmail = rcmail::get_instance();
        $bypass = $rcmail->config->get('turnstile_bypass_ips', []);
        $ip     = $_SERVER['REMOTE_ADDR'] ?? '';

        return in_array($ip, $bypass);
    }
}
