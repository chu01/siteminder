<?php

/**
 * @file
 * Contains \Drupal\siteminder\Form\SettingsForm.
 */

namespace Drupal\siteminder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder for the simplesamlphp_auth basic settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'siteminder_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['siteminder.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('siteminder.settings');

    $form['general'] = array(
      '#type' => 'fieldset',
      '#title' => $this->t('General settings'),
      '#collapsible' => FALSE,
    );
    $form['general']['username_mapping'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Which variable from siteminder should be used as user\'s name'),
      '#default_value' => $config->get('user.username_mapping'),
      '#description' => $this->t('Define the name of the variable that your Siteminder configuration will use to pass the authenticated user name.'),
    );
    $form['general']['username_prefix'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Strip prefix'),
      '#default_value' => $config->get('user.prefix_strip'),
      '#description' => $this->t('Enable this if your Siteminder configuration adds a prefix to the username and you do not want it used in the username.'),
    );
    $form['general']['username_domain'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Strip domain'),
      '#default_value' => $config->get('user.domain_strip'),
      '#description' => $this->t('Enable this if your Siteminder configuration adds a domain to the username and you do not want it used in the username.'),
    );
    $form['general']['email_mapping'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Which variable from siteminder should be used as user mail address'),
      '#default_value' => $config->get('user.mail_mapping'),
      '#description' => $this->t('Define the name of the variable that your Siteminder configuration will use to pass the authenticated user name.'),
    );
    $form['general']['role_mapping'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Automatic role population from Siteminder variable'),
      '#default_value' => $config->get('user.role_mapping'),
      '#description' => $this->t('A pipe separated list of rules. Each rule consists of a Drupal role id, a siteminder variable name, an operation and a value to match. <i>e.g. role_id1:attribute_name,operation,value|role_id2:attribute_name2,operation,value... etc</i><br /><br />Each operation may be either "@", "@=" or "~=". <ul><li>"=" requires the value exactly matches the attribute;</li><li>"@=" requires the portion after a "@" in the attribute to match the value;</li><li>"~=" allows the value to match any part of any element in the attribute array.</li></ul>For instance:<br /><i>staff:eduPersonPrincipalName,@=,uninett.no;affiliation,=,employee|admin:mail,=,andreas@uninett.no</i><br />would ensure any user with an eduPersonPrinciplaName siteminder variable matching .*@uninett.no would be assigned a staff role and the user with the mail attribute exactly matching andreas@uninett.no would assume the admin role.'),
    );
    $form['general']['role_evaluate_everytime'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Re-evaluate roles every time the user logs in'),
      '#default_value' => $config->get('user.role_evaluate_everytime'),
      '#description' => $this->t('NOTE: This means users could lose any roles that have been assigned manually in Drupal.'),
    );
    $form['general']['logout_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Provide the Siteminder logout URL'),
      '#default_value' => $config->get('logout_url'),
      '#required' => TRUE,
      '#description' => $this->t('Specify a Siteminder logout URL.'),
    );
    $form['general']['siteminder_cookie'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Provide the Siteminder Client Side Cookie Name'),
      '#default_value' => $config->get('siteminder_cookie'),
      '#required' => TRUE,
      '#description' => $this->t('Specify a Siteminder Cookie Name set on the client side.'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('siteminder.settings');

    $config->set('user.username_mapping', $form_state->getValue('username_mapping'));
    $config->set('user.prefix_strip', $form_state->getValue('username_prefix'));
    $config->set('user.domain_strip', $form_state->getValue('username_domain'));
    $config->set('user.mail_mapping', $form_state->getValue('email_mapping'));
    $config->set('user.role_mapping', $form_state->getValue('role_mapping'));
    $config->set('user.role_evaluate_everytime', $form_state->getValue('role_evaluate_everytime'));
    $config->set('logout_url', $form_state->getValue('logout_url'));
    $config->set('siteminder_cookie', $form_state->getValue('siteminder_cookie'));
    $config->save();
  }

}
