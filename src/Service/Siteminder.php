<?php

/**
 * @file
 * Contains \Drupal\siteminder\Service\Siteminder.
 */

namespace Drupal\siteminder\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Service to check the Siteminder header information.
 */
class Siteminder {

    /**
     * A configuration object.
     *
     * @var \Drupal\Core\Config\ImmutableConfig
     */
    protected $config;

    /**
     * {@inheritdoc}
     *
     * @param ConfigFactoryInterface $config_factory
     *   The configuration factory.
     */
    public function __construct(ConfigFactoryInterface $config_factory) {
        $this->config = $config_factory->get('siteminder.settings');
    }

    /**
     * Check whether user is authenticated and user information is available in the Header
     *
     */
    public function isAuthenticated() {
        return $this->getAuthname();
    }

    /**
     * Gets the unique Mapping form the Header.
     *
     * @return string
     *   The authname.
     */
    public function getAuthname() {
        return $this->getSiteminderHeaderVariable($this->config->get('user.username_mapping'));
    }

    /**
     * Gets the name attribute.
     *
     * @return string
     *   The name attribute.
     */
    public function getDefaultName() {
        return $this->getSiteminderHeaderVariable($this->config->get('user.user_mapping'));
    }

    /**
     * Gets the mail attribute.
     *
     * @return string
     *   The mail attribute.
     */
    public function getDefaultEmail() {
        return $this->getSiteminderHeaderVariable($this->config->get('user.mail_mapping'));
    }
    /**
     * Get a specific Siteminder Variable from the Header.
     *
     */
    public function getSiteminderHeaderVariables() {
        $uniqiue_variable = $this->config->get('user.username_mapping');
        if (!empty($_SERVER[$uniqiue_variable])) {
          return $_SERVER;
        }
    }
    /**
     * Get a specific Siteminder Variable from the Header and return the matached attribute.
     *
     */
    public function getSiteminderHeaderVariable($variable) {
        $header_variables = $this->getSiteminderHeaderVariables();
        if (isset($variable)) {
            if (!empty($header_variables[$variable])) {
                return $header_variables[$variable];
            }
        }
    }
    /**
     * Log a user out from drupal and Siteminder instance.
     *
     * @param string $redirect_path
     *   The path to redirect to after logout.
     */
    public function logout($logout_url = NULL) {
        if (isset($logout_url)) {
            $application_url = (!empty($_SERVER['HTTPS'])) ? "https://".$_SERVER['SERVER_NAME'] : "http://".$_SERVER['SERVER_NAME'];
            $logout_url  = $logout_url ."?referrer=". $application_url;
            $response = new RedirectResponse($logout_url);
            $response->send();
        }
    }
}
