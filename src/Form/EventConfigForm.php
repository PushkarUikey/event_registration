<?php

namespace Drupal\event_registration\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;

class EventConfigForm extends FormBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  public function getFormId() {
    return 'event_registration_config_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['event_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event Name'),
      '#required' => TRUE,
    ];

    $form['category'] = [
      '#type' => 'select',
      '#title' => $this->t('Category'),
      '#options' => [
        'Online Workshop' => 'Online Workshop',
        'Hackathon' => 'Hackathon',
        'Conference' => 'Conference',
        'One-day Workshop' => 'One-day Workshop',
      ],
      '#required' => TRUE,
    ];

    $form['reg_start_date'] = ['#type' => 'date', '#title' => $this->t('Registration Start Date'), '#required' => TRUE];
    $form['reg_end_date'] = ['#type' => 'date', '#title' => $this->t('Registration End Date'), '#required' => TRUE];
    $form['event_date'] = ['#type' => 'date', '#title' => $this->t('Event Date'), '#required' => TRUE];

    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save Event')];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!preg_match('/^[a-zA-Z0-9\s]+$/', $form_state->getValue('event_name'))) {
      $form_state->setErrorByName('event_name', $this->t('Event Name contains illegal characters.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->database->insert('event_details')
      ->fields([
        'event_name' => $form_state->getValue('event_name'),
        'category' => $form_state->getValue('category'),
        'reg_start_date' => $form_state->getValue('reg_start_date'),
        'reg_end_date' => $form_state->getValue('reg_end_date'),
        'event_date' => $form_state->getValue('event_date'),
      ])
      ->execute();

    $this->messenger()->addMessage($this->t('Event created successfully.'));
  }
}