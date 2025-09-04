<?php

namespace Drupal\author_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Mail\MailManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for OTP verification during user registration.
 *
 * This form lets users enter and validate their one-time password
 * before their account is created.
 */
class VerifyOtpForm extends FormBase {

  /**
   * The private temp store for storing OTP data.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $tempStore;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * Constructs a new VerifyOtpForm object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The temp store factory.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory, MailManagerInterface $mail_manager) {
    $this->tempStore = $temp_store_factory->get('author_registration');
    $this->mailManager = $mail_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('plugin.manager.mail')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'author_registration_verify_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $mail = \Drupal::request()->query->get('mail');

    $form['mail'] = [
      '#type' => 'value',
      '#value' => $mail,
    ];

    $form['otp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Enter the 6-digit code'),
      '#required' => TRUE,
      '#size' => 6,
      '#maxlength' => 6,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify & Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $mail = $form_state->getValue('mail');
    $submitted = $this->tempStore->get($mail);

    if (!$submitted) {
      $form_state->setErrorByName('otp', $this->t('No pending registration found. Please start again.'));
      return;
    }

    // OTP expires after 10 minutes (600 seconds).
    if (\Drupal::time()->getRequestTime() - $submitted['created'] > 600) {
      $form_state->setErrorByName('otp', $this->t('Code expired. Please restart registration.'));
    }

    if ($submitted && $submitted['otp'] !== $form_state->getValue('otp')) {
      $form_state->setErrorByName('otp', $this->t('Invalid code.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mail = $form_state->getValue('mail');
    $submitted = $this->tempStore->get($mail);

    // Create the user account.
    $user = User::create();
    $user->setEmail($submitted['mail']);
    $user->setUsername($submitted['mail']);
    $user->setPassword($submitted['pass']);
    $user->set('status', 0);
    $user->set('field_full_name', $submitted['full_name']);
    $user->save();

    // Assign role based on author type.
    $role = $submitted['author_type'] === 'blogger' ? 'blogger' : 'guest_blogger';
    if ($user->hasField('roles')) {
      $user->addRole($role);
      $user->save();
    }

    // Notify admin about new registration.
    $params_admin = [
      'subject' => $this->t('New author signup (pending approval)'),
      'message' => $this->t("Name: @n\nEmail: @e\nType: @t", [
        '@n' => $submitted['full_name'],
        '@e' => $submitted['mail'],
        '@t' => $submitted['author_type'] === 'blogger' ? 'Blogger' : 'Guest Blogger',
      ]),
    ];
    $admin_mail = \Drupal::config('system.site')->get('mail') ?: 'aayush.pathak@innoraft.com';
    $this->mailManager->mail('author_registration', 'admin_notify', $admin_mail, \Drupal::languageManager()->getDefaultLanguage()->getId(), $params_admin);

    // Notify user.
    $params_user = [
      'subject' => $this->t('Thank you for your submission'),
      'message' => $this->t('Thank you for your submission. We will get back to you soon.'),
    ];
    $this->mailManager->mail('author_registration', 'thanks', $submitted['mail'], \Drupal::languageManager()->getDefaultLanguage()->getId(), $params_user);

    // Clear temp store and redirect.
    $this->tempStore->delete($mail);
    $this->messenger()->addStatus($this->t('Email verified. Your account is pending admin approval.'));
    $form_state->setRedirect('<front>');
  }

}
