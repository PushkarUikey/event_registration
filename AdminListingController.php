<?php

namespace Drupal\event_registration\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Response;

class AdminListingController extends ControllerBase {

  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'));
  }

  /**
   * Renders the Admin Filter Form.
   */
  public function content() {
    $form = \Drupal::formBuilder()->getForm('Drupal\event_registration\Form\AdminFilterForm');
    return $form;
  }

  /**
   * Exports data to CSV.
   */
  public function exportCsv() {
      $query = $this->database->select('event_registrations', 'r');
      $query->join('event_details', 'e', 'r.event_id = e.id');
      $query->fields('r', ['full_name', 'email', 'college', 'department', 'created'])
            ->fields('e', ['event_name', 'event_date', 'category']);
      $results = $query->execute()->fetchAll();

      $handle = fopen('php://temp', 'r+');
      // CSV Headers
      fputcsv($handle, ['Name', 'Email', 'College', 'Department', 'Event', 'Date', 'Category', 'Submitted']);

      foreach ($results as $row) {
          fputcsv($handle, [
              $row->full_name,
              $row->email,
              $row->college,
              $row->department,
              $row->event_name,
              $row->event_date,
              $row->category,
              date('Y-m-d H:i:s', $row->created)
          ]);
      }

      rewind($handle);
      $csv_data = stream_get_contents($handle);
      fclose($handle);

      $response = new Response($csv_data);
      $response->headers->set('Content-Type', 'text/csv');
      $response->headers->set('Content-Disposition', 'attachment; filename="registrations.csv"');

      return $response;
  }
}