<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a registration form for events.
 */
class RegistrationForm extends FormBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected $mailManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new RegistrationForm.
   */
  public function __construct(Connection $database, MailManagerInterface $mail_manager, ConfigFactoryInterface $config_factory) {
    $this->database = $database;
    $this->mailManager = $mail_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('plugin.manager.mail'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_registration_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // 1. Full Name
    $form['full_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
    ];

    // 2. Email Address
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#required' => TRUE,
    ];

    // 3. College Name
    $form['college'] = [
      '#type' => 'textfield',
      '#title' => $this->t('College Name'),
      '#required' => TRUE,
    ];

    // 4. Department
    $form['department'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Department'),
      '#required' => TRUE,
    ];

    // 5. Category Dropdown (Triggers AJAX)
    // In a real scenario, these options might come from the DB too, but hardcoded is fine for the requirement.
    $categories = ['Technical' => 'Technical', 'Non-Technical' => 'Non-Technical', 'Hackathon' => 'Hackathon', 'Workshop' => 'One-day Workshop', 'Conference' => 'Conference'];
    
    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => $categories,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => '::updateDateDropdown',
        'wrapper' => 'date-wrapper',
        'event' => 'change',
      ],
    ];

    // 6. Event Date Dropdown (Dependent on Category)
    $form['event_date_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'date-wrapper'],
    ];

    $category = $form_state->getValue('category');
    $date_options = [];

    if ($category) {
      // Query DB for dates associated with this category
      $query = $this->database->select('event_details', 'e');
      $query->fields('e', ['event_date']);
      $query->condition('e.category', $category);
      $query->distinct();
      $results = $query->execute()->fetchAll();

      foreach ($results as $row) {
        $date_options[$row->event_date] = $row->event_date;
      }
    }

    $form['event_date_wrapper']['event_date'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Date'),
      '#options' => $date_options,
      '#empty_option' => $this->t('- Select Date -'),
      '#validated' => TRUE, // Important for AJAX
      '#ajax' => [
        'callback' => '::updateEventNameDropdown',
        'wrapper' => 'event-name-wrapper',
        'event' => 'change',
      ],
      // Hide if empty/no category selected
      '#access' => !empty($date_options) || $category, 
    ];

    // 7. Event Name Dropdown (Dependent on Date)
    $form['event_name_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-name-wrapper'],
    ];

    $selected_date = $form_state->getValue('event_date');
    $event_options = [];

    if ($category && $selected_date) {
      $query = $this->database->select('event_details', 'e');
      $query->fields('e', ['id', 'event_name']);
      $query->condition('e.category', $category);
      $query->condition('e.event_date', $selected_date);
      $results = $query->execute()->fetchAll();

      foreach ($results as $row) {
        $event_options[$row->id] = $row->event_name;
      }
    }

    $form['event_name_wrapper']['event_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Name'),
      '#options' => $event_options,
      '#validated' => TRUE,
      // Hide if empty
      '#access' => !empty($event_options),
    ];

    // 8. Submit Button
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // 1. Validate Text Fields (No Special Characters)
    // Allowed: Letters (a-z), Numbers (0-9), Spaces.
    $text_fields = ['full_name' => 'Full Name', 'college' => 'College Name', 'department' => 'Department'];
    
    foreach ($text_fields as $field_key => $field_label) {
      $value = $form_state->getValue($field_key);
      // Check if value contains anything OTHER than letters, numbers, and spaces
      if (!preg_match('/^[a-zA-Z0-9\s]+$/', $value)) {
        $form_state->setErrorByName($field_key, $this->t('@label cannot contain special characters.', ['@label' => $field_label]));
      }
    }

    // 2. Validate Duplicate Registration (Email + Event Date)
    $email = $form_state->getValue('email');
    $event_id = $form_state->getValue('event_id');

    if ($email && $event_id) {
      // Check database: Has this email already registered for this specific event ID?
      $query = $this->database->select('event_registrations', 'r');
      $query->fields('r', ['id']);
      $query->condition('r.email', $email);
      $query->condition('r.event_id', $event_id);
      $result = $query->execute()->fetchField();

      if ($result) {
        $form_state->setErrorByName('email', $this->t('This email address is already registered for this event.'));
      }
    }
  }

  /**
   * AJAX callback for the Date dropdown.
   */
  public function updateDateDropdown(array &$form, FormStateInterface $form_state) {
    return $form['event_date_wrapper'];
  }

  /**
   * AJAX callback for the Event Name dropdown.
   */
  public function updateEventNameDropdown(array &$form, FormStateInterface $form_state) {
    return $form['event_name_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $event_id = $form_state->getValue('event_id');

    // 1. Insert into Database
    try {
      $this->database->insert('event_registrations')
        ->fields([
          'full_name' => $form_state->getValue('full_name'),
          'email' => $form_state->getValue('email'),
          'college' => $form_state->getValue('college'),
          'department' => $form_state->getValue('department'),
          'event_id' => $event_id,
          'created' => \Drupal::time()->getRequestTime(),
        ])
        ->execute();
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Database error: @error', ['@error' => $e->getMessage()]));
      return;
    }

    // 2. Fetch Event Name for Email
    $event_name = '';
    try {
        $event_name = $this->database->query("SELECT event_name FROM {event_details} WHERE id = :id", [':id' => $event_id])->fetchField();
    } catch (\Exception $e) {
        // Ignore if fetch fails
    }

    // 3. Email Logic (Wrapped in Try-Catch to prevent crash on Localhost)
    try {
        $module_config = $this->configFactory->get('event_registration.settings');
        $params = [
            'name' => $form_state->getValue('full_name'),
            'email' => $form_state->getValue('email'),
            'event_name' => $event_name,
            'category' => $form_state->getValue('category'),
            'date' => $form_state->getValue('event_date'),
        ];

        // Attempt to send to User
        $this->mailManager->mail('event_registration', 'registration_confirm', $params['email'], 'en', $params);

        // Attempt to send to Admin
        if ($module_config->get('enable_notifications')) {
            $this->mailManager->mail('event_registration', 'admin_notification', $module_config->get('admin_email'), 'en', $params);
        }
    }
    catch (\Exception $e) {
        // Log the error silently so the user still sees "Success"
        \Drupal::logger('event_registration')->error('Email failed to send: @error', ['@error' => $e->getMessage()]);
    }

    // 4. Show Success Message
    $this->messenger()->addMessage($this->t('Registration successful!'));
  }

}