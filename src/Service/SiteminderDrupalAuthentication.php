<?php

/**
 * @file
 * Contains \Drupal\siteminder\Service\SiteminderDrupalAuthentication.
 */

namespace Drupal\siteminder\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\user\UserInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\externalauth\ExternalAuthInterface;

/**
 * After Successful login with Siteminder authentication with Drupal users.
 */
class SiteminderDrupalAuthentication {

  /**
   * Siteminder helper.
   *
   * @var $siteminder
   */
  protected $siteminder;
  
  /**
   * A configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The ExternalAuth service.
   *
   * @var \Drupal\externalauth\ExternalAuth
   */
  protected $externalauth;

  /**
   * The currently logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   *
   * @param Siteminder $siteminder_info
   *   The Siteminder helper service.
   * @param ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param LoggerInterface $logger
   *   A logger instance.
   * @param ExternalAuthInterface $externalauth
   *   The ExternalAuth service.
   * @param AccountInterface $account
   *   The currently logged in user.
   */
  public function __construct(Siteminder $siteminder_info, ConfigFactoryInterface $config_factory, EntityManagerInterface $entity_manager, ExternalAuthInterface $externalauth, AccountInterface $account) {
    $this->siteminder = $siteminder_info;
    $this->config = $config_factory->get('siteminder.settings');
    $this->entityManager = $entity_manager;
    $this->externalauth = $externalauth;
    $this->currentUser = $account;
  }

  /**
   * Log in and optionally register a user based on the authname provided.
   *
   * @param string $authname
   *   The authentication name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The logged in Drupal user.
   */
  public function externalLoginRegister($authname) {
    $account = $this->externalauth->login($authname, 'siteminder');
    if (!$account) {
      $account = $this->externalRegister($authname);
    }

    if ($account) {
      // Determine if roles should be evaluated upon login.
      if ($this->config->get('user.role_evaluate_everytime')) {
        $this->roleMatchAdd($account);
      }
    }

    return $account;
  }

  /**
   * Registers a user locally as one authenticated by the Siteminder.
   *
   * @param string $authname
   *   The authentication name.
   *
   * @return \Drupal\Core\Entity\EntityInterface|bool
   *   The registered Drupal user.
   *
   * @throws \Exception
   *   An ExternalAuth exception.
   */
  public function externalRegister($authname) {
    $account = FALSE;

    // Check whether the user with this authname already exists in the Drupal database.
    $existing_user = $this->entityManager->getStorage('user')->loadByProperties(array('name' => $authname));
    $existing_user = $existing_user ? reset($existing_user) : FALSE;
    if ($existing_user) {
      $this->externalauth->linkExistingAccount($authname, 'siteminder', $existing_user);
      $account = $existing_user;      
    }

    if (!$account) {
      // Create the new user.
      try {
        $account = $this->externalauth->register($authname, 'siteminder');
      }
      catch (\Exception $ex) {
        watchdog_exception('siteminder', $ex);
        drupal_set_message(t('Error registering user: An account with this username already exists.'), 'error');
      }
    }

    if ($account) {
      $this->synchronizeUserAttributes($account, TRUE);
      return $this->externalauth->userLoginFinalize($account, $authname, 'siteminder');
    }
  }

  /**
   * Synchronizes user data if enabled.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The Drupal account to synchronize attributes on.
   * @param bool $force
   *   Define whether to force syncing of the user attributes, regardless of
   *   SimpleSAMLphp settings.
   */
  public function synchronizeUserAttributes(AccountInterface $account, $force = FALSE) {
    $mail_mapping = $force || $this->config->get('user.mail_mapping');
    $sync_user_name = $force || $this->config->get('user.username_mapping');

    if ($sync_user_name) {
      $name = $this->siteminder->getDefaultName();
      if ($name) {
        $existing = FALSE;
        $account_search = $this->entityManager->getStorage('user')->loadByProperties(array('name' => $name));
        if ($existing_account = reset($account_search)) {
          if ($this->currentUser->id() != $existing_account->id()) {
            $existing = TRUE;
            drupal_set_message(t('Error synchronizing username: an account with this username already exists.'), 'error');
          }
        }

        if (!$existing) {
          $account->setUsername($name);
        }
      }
      else {
        drupal_set_message(t('Error synchronizing username: no username is provided by SAML.'), 'error');
      }
    }

    if ($mail_mapping) {
      $mail = $this->siteminder->getDefaultEmail();
      if ($mail) {
        $account->setEmail($mail);
      }
      else {
        drupal_set_message(t('Error synchronizing mail: no email address is provided by SAML.'), 'error');
      }
    }

    if ($mail_mapping || $sync_user_name) {
      $account->save();
    }
  }

  /**
   * Adds roles to user accounts.
   *
   * @param UserInterface $account
   *   The Drupal user to add roles to.
   */
  public function roleMatchAdd(UserInterface $account) {
    // Get matching roles based on retrieved SimpleSAMLphp attributes.
    $matching_roles = $this->getMatchingRoles();
//print_r($matching_roles);
      //exit;
    if ($matching_roles) {
      foreach ($matching_roles as $role_id) {
        $account->addRole($role_id);
      }
      $account->save();
    }
  }

  /**
   * Get matching user roles to assign to user.
   *
   * Matching roles are based on retrieved SimpleSAMLphp attributes.
   *
   * @return array
   *   List of matching roles to assign to user.
   */
  public function getMatchingRoles() {
    $roles = array();
    // Obtain the role map stored. The role map is a concatenated string of
    // rules which, when SimpleSAML attributes on the user match, will add
    // roles to the user.
    // The full role map string, when mapped to the variables below, presents
    // itself thus:
    // $role_id:$key,$op,$value;$key,$op,$value;|$role_id:$key,$op,$value etc.
    if ($rolemap = $this->config->get('user.role_mapping')) {
        //print_r($rolemap);
        //exit;
      foreach (explode('|', $rolemap) as $rolerule) {
        list($role_id, $role_eval) = explode(':', $rolerule, 2);

        foreach (explode(';', $role_eval) as $role_eval_part) {
          if ($this->evalRoleRule($role_eval_part)) {
            $roles[$role_id] = $role_id;
          }
        }
      }
    }

    //$attributes = $this->siteminder->getSiteminderHeaderVariables();
    //\Drupal::modulehandler()->alter('simplesamlphp_auth_user_roles', $roles, $attributes);
    return $roles;
  }

  /**
   * Determines whether a role should be added to an account.
   *
   * @param string $role_eval_part
   *   Part of the role evaluation rule.
   *
   * @return bool
   *   Whether a role should be added to the Drupal account.
   */
  protected function evalRoleRule($role_eval_part) {
    list($variable_key, $operator, $value) = explode(',', $role_eval_part);
    $header_variables = $this->siteminder->getSiteminderHeaderVariables();
    if (!array_key_exists($variable_key, $header_variables)) {
      return FALSE;
    }
    $match_variable = $header_variables[$variable_key];
    // A '=' requires the $value exactly matches the $attribute, A '@='
    // requires the portion after a '@' in the $attribute to match the
    // $value and a '~=' allows the value to match any part of any
    // element in the $attribute array.
    switch ($operator) {
      case '=':
        return ($value == $match_variable);

      case '@=':
        list($before, $after) = explode('@', array_shift($match_variable));
        return ($after == $value);

      case '~=':
        return array_filter($match_variable, function($subattr) use ($value) {
          return strpos($subattr, $value) !== FALSE;
        });
    }
  }

}
