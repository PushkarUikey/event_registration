<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

class AdminFilterForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  public function getFormId() {
    return 'event_registration_admin_filter_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // 1. Export Button
    $form['export'] = [
      '#type' => 'link',
      '#title' => $this->t('Export to CSV'),
      '#url' => \Drupal\Core\Url::fromRoute('event_registration.export_csv'),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => TRUE,
    ];

    // 2. AJAX Dropdowns
    $dates = $this->database->query("SELECT DISTINCT event_date FROM {event_details}")->fetchCol();
    $date_options = $dates ? array_combine($dates, $dates) : [];

    $form['filters']['date_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Date'),
      '#options' => $date_options,
      '#empty_option' => $this->t('- Any -'),
      '#ajax' => [
        'callback' => '::updateEventFilter',
        'wrapper' => 'event-filter-wrapper',
      ],
    ];

    $form['filters']['event_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'event-filter-wrapper'],
    ];

    $selected_date = $form_state->getValue('date_filter');
    $event_options = [];

    if ($selected_date) {
        $events = $this->database->query("SELECT id, event_name FROM {event_details} WHERE event_date = :date", [':date' => $selected_date])->fetchAllKeyed();
        $event_options = $events;
    }

    $form['filters']['event_wrapper']['event_id_filter'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Event'),
      '#options' => $event_options,
      '#empty_option' => $this->t('- Any -'),
      '#ajax' => [
        'callback' => '::updateTable',
        'wrapper' => 'table-wrapper',
      ],
    ];

    // 3. Results Table
    $form['table_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'table-wrapper'],
    ];

    $header = [
        'full_name' => $this->t('Name'),
        'email' => $this->t('Email'),
        'college' => $this->t('College'),
        'event_name' => $this->t('Event'),
        'created' => $this->t('Submitted'),
    ];

    // Build Query based on filters
    $query = $this->database->select('event_registrations', 'r');
    $query->join('event_details', 'e', 'r.event_id = e.id');
    $query->fields('r', ['full_name', 'email', 'college', 'created'])
          ->fields('e', ['event_name']);

    // Apply Filter: Date
    if ($selected_date) {
        $query->condition('e.event_date', $selected_date);
    }

    // Apply Filter: Event ID (Get input specifically to handle nested ajax)
    $input = $form_state->getUserInput();
    $selected_event = $input['event_id_filter'] ?? $form_state->getValue('event_id_filter');

    if ($selected_event) {
        $query->condition('r.event_id', $selected_event);
    }

    $results = $query->execute()->fetchAll();

    // Participant Count
    $form['table_wrapper']['count'] = [
        '#markup' => '<h3>Total Participants: ' . count($results) . '</h3>',
    ];

    $rows = [];
    foreach ($results as $row) {
        $rows[] = [
            'full_name' => $row->full_name,
            'email' => $row->email,
            'college' => $row->college,
            'event_name' => $row->event_name,
            'created' => date('Y-m-d H:i', $row->created),
        ];
    }

    $form['table_wrapper']['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('No registrations found.'),
    ];

    return $form;
  }

  public function updateEventFilter(array &$form, FormStateInterface $form_state) {
      return $form['filters']['event_wrapper'];
  }

  public function updateTable(array &$form, FormStateInterface $form_state) {
      return $form['table_wrapper'];
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Filter form doesn't need submit logic, just AJAX
  }
}