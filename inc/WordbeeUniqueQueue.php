<?php

/**
 * @file
 * Contains WordbeeUniqueQueue.
 */

/**
 * Extends SystemQueue making items unique.
 */
class WordbeeUniqueQueue extends SystemQueue {

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    $data_serialized = serialize($data);
    return (bool) db_merge('queue')
      ->key(array(
        'name' => $this->name,
        'data' => $data_serialized,
      ))
      ->fields(array(
        'name' => $this->name,
        'data' => $data_serialized,
        'created' => time(),
      ))
      ->execute();
  }

}
