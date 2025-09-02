<?php

namespace Drupal\author_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Messenger\MessengerTrait;

/**
 * Provides a form for becoming an author with email + OTP verification.
 */
class BecomeAuthorForm extends FormBase {
  use MessengerTrait;

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
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs a new BecomeAuthorForm object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The private temp store factory.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    PrivateTempStoreFactory $temp_store_factory,
    MailManagerInterface $mail_manager,
    EntityTypeManagerInterface $entity_type_manager,
    TimeInterface $time,
    LanguageManagerInterface $language_manager,
  ) {
    $this->tempStore = $temp_store_factory->get('author_registration');
    $this->mailManager = $mail_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->time = $time;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('tempstore.private'),
      $container->get('plugin.manager.mail'),
      $container->get('entity_type.manager'),
      $container->get('datetime.time'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'author_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    $form['mail'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address (used for login)'),
      '#required' => TRUE,
    ];

    $form['pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['author_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Author Type'),
      '#options' => [
        'blogger' => $this->t('Blogger'),
        'guest' => $this->t('Guest Blogger'),
      ],
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $mail = $form_state->getValue('mail');

    $user_storage = $this->entityTypeManager->getStorage('user');
    $users = $user_storage->loadByProperties(['mail' => $mail]);
    $account = reset($users);

    if ($account) {
      $form_state->setErrorByName('mail', $this->t('An account with this email already exists.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $data = [
      'full_name' => $form_state->getValue('full_name'),
      'mail' => $form_state->getValue('mail'),
      'pass' => $form_state->getValue('pass'),
      'author_type' => $form_state->getValue('author_type'),
    ];

    $otp = random_int(100000, 999999);
    $data['otp'] = (string) $otp;
    $data['created'] = $this->time->getRequestTime();
    $data['expires'] = $data['created'] + 600;

    $this->tempStore->set($data['mail'], $data);

    $params = [
      'subject' => $this->t('Your verification code'),
      'message' => $this->t('Your OTP is @otp. It expires in 10 minutes.', ['@otp' => $otp]),
    ];
    $this->mailManager->mail(
      'author_registration',
      'otp',
      $data['mail'],
      $this->languageManager->getDefaultLanguage()->getId(),
      $params
    );

    $this->messenger()->addStatus(
      $this->t('We sent a verification code to @mail. Please check your inbox.', ['@mail' => $data['mail']])
    );

    $form_state->setRedirectUrl(
      Url::fromRoute('author_registration.verify', [], ['query' => ['mail' => $data['mail']]])
    );
  }

}
