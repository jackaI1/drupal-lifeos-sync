<?php

namespace Drupal\lifeos_sync;

/**
 * Validates a whole public snapshot and converts dates to Drupal UTC storage.
 */
final class FeedNormalizer {

  /**
   * Returns normalized queue records, or rejects the entire snapshot.
   */
  public function normalize(array $feed): array {
    if (!isset($feed['timeZone'], $feed['schedule'], $feed['tasks']) || !is_string($feed['timeZone']) || !is_array($feed['schedule']) || !array_is_list($feed['schedule']) || !is_array($feed['tasks']) || !array_is_list($feed['tasks'])) {
      throw new \InvalidArgumentException('Expected timeZone, schedule and tasks lists.');
    }
    try {
      $timezone = new \DateTimeZone($feed['timeZone']);
    }
    catch (\Exception $exception) {
      throw new \InvalidArgumentException('Invalid feed timeZone.', 0, $exception);
    }
    if (count($feed['schedule']) + count($feed['tasks']) > 500) {
      throw new \InvalidArgumentException('The feed exceeds 500 records.');
    }
    $items = [];
    foreach (['schedule', 'tasks'] as $collection) {
      foreach ($feed[$collection] as $record) {
        if (!is_array($record)) {
          throw new \InvalidArgumentException('Each record must be an object.');
        }
        $kind = $collection === 'tasks' ? 'task' : 'schedule';
        $id = $kind . ':' . $this->text($record['id'] ?? NULL, TRUE, 200);
        if (isset($items[$id])) {
          throw new \InvalidArgumentException('Duplicate source ID.');
        }
        $status = $this->text($record['status'] ?? NULL, TRUE);
        $task_statuses = [
          'todo', 'in_progress', 'blocked', 'done',
          'completed', 'cancelled', 'archived',
        ];
        $allowed = $kind === 'task' ? $task_statuses : ['confirmed', 'cancelled'];
        if (!in_array($status, $allowed, TRUE)) {
          throw new \InvalidArgumentException('Unsupported source status.');
        }
        $when = NULL;
        $end = NULL;
        if ($kind === 'schedule') {
          $when = $this->dateTime($record['start']['dateTime'] ?? NULL);
          $end = $this->dateTime($record['end']['dateTime'] ?? NULL);
          if ($end <= $when) {
            throw new \InvalidArgumentException('Schedule end must follow start.');
          }
        }
        elseif (!empty($record['dueAt'])) {
          $when = $this->dateTime($record['dueAt']);
        }
        elseif (!empty($record['dueDate'])) {
          $date = $record['dueDate'];
          if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) {
            throw new \InvalidArgumentException('dueDate must be YYYY-MM-DD.');
          }
          $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $timezone);
          if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Invalid dueDate.');
          }
          $when = $parsed->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
        }
        $items[$id] = [
          'id' => $id,
          'title' => $this->text($record['title'] ?? NULL, TRUE),
          'kind' => $kind,
          'when' => $when,
          'end' => $end,
          'course' => $this->text($record['courseCode'] ?? ''),
          'location' => $this->text($record['location'] ?? ''),
          'status' => $status,
          'published' => !in_array($status, ['done', 'completed', 'cancelled', 'archived'], TRUE),
        ];
      }
    }
    return array_values($items);
  }

  /**
   * Validates a string without silently truncating source data.
   */
  private function text(mixed $value, bool $required = FALSE, int $limit = 255): string {
    if (!is_string($value) || ($required && trim($value) === '') || mb_strlen($value) > $limit) {
      throw new \InvalidArgumentException('Missing, invalid or oversized text field.');
    }
    return $value;
  }

  /**
   * Requires an explicit offset and rejects PHP's permissive date rollover.
   */
  private function dateTime(mixed $value): string {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
      throw new \InvalidArgumentException('Expected an ISO 8601 timestamp with offset.');
    }
    try {
      $date = new \DateTimeImmutable($value);
      $errors = \DateTimeImmutable::getLastErrors();
      if ($errors && ($errors['warning_count'] || $errors['error_count'])) {
        throw new \InvalidArgumentException('Invalid timestamp.');
      }
      return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s');
    }
    catch (\Exception $exception) {
      throw new \InvalidArgumentException('Invalid timestamp.', 0, $exception);
    }
  }

}
