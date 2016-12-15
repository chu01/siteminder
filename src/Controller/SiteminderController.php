<?php

/**
 * Contains \Drupal\siteminder\Controller\SiteminderController.
 * @file
 */

namespace Drupal\siteminder\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\siteminder\Service\Siteminder;
use Drupal\siteminder\Service\SiteminderDrupalAuthentication;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;


/**
 * Controller for register/login Siteminder variables.
 */
class SiteminderController extends ControllerBase implements ContainerInjectionInterface {
  
  /**
   * The Siteminder Authentication helper service.
   *
   * @var \Drupal\siteminder\Service\Siteminder
   */
  public $siteminder;

  /**
   * The Siteminder Drupal Authentication service.
   *
   * @var \Drupal\siteminder\Service\SiteminderDrupalAuthentication
   */
  public $siteminderDrupalauth;
  
  /**
   * The current account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * A configuration object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * {@inheritdoc}
   *
   * @param Siteminder $siteminder
   *   The Siteminder Authentication helper service.
   * @param SiteminderDrupalAuthentication $siteminder_drupalauth
   *   The Siteminder Drupal Authentication service.
   * @param AccountInterface $account
   *   The current account.
   * @param ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(Siteminder $siteminder, SiteminderDrupalAuthentication $siteminder_drupalauth, AccountInterface $account, ConfigFactoryInterface $config_factory) {
	$this->siteminder = $siteminder;
    $this->siteminderDrupalauth = $siteminder_drupalauth;    
    $this->account = $account;    
    $this->config = $config_factory->get('siteminder.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('siteminder.siteminderhelper'),
      $container->get('siteminder.drupalauthentication'),      
      $container->get('current_user'),      
      $container->get('config.factory')
    );
  }

  /**
   * Logs the user in via Siteminder.
   *
   * @return RedirectResponse
   *   A redirection to application Homepage.
   */
  public function authenticate() {
    // The user is not logged into Drupal.
	if ($this->account->isAuthenticated()) {
      // Check to see if the User is logged in to the Siteminder, and Drupal are same user.
      // If not logout the Drupal user and execute this process again	  
      if ($this->siteminder->isAuthenticated()) {
	    if ($this->siteminder->getAuthname() != $this->account->getAccountName()) {
		  session_destroy();	  
          return $this->redirect('siteminder.sitemider_login');
        }		  
      }	  
	}
    // The user is not logged into Drupal.
    if ($this->account->isAnonymous()) {
      // User is logged in to the Siteminder, but not to Drupal.
      if ($this->siteminder->isAuthenticated()) {
        // Get unique identifier from Siteminder Header.
        $authname = $this->siteminder->getAuthname();
        if (!empty($authname)) {
          // User logged into Siteminder Agent and we got the unique identifier, so try to log into Drupal.
          // Check to see whether the external user exists in Drupal. If they do not exist, create one and log in the user.
          $this->siteminderDrupalauth->externalLoginRegister($authname);
        }
      }      
    }
    //Redirect the User to the homepage	
    $redirect = '/';    
    $response = new RedirectResponse($redirect, RedirectResponse::HTTP_FOUND);
    return $response;    
  }
}
