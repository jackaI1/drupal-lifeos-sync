<?php

namespace Drupal\lifeos_sync;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Fetches only a bounded public JSON snapshot.
 */
final class FeedClient {

  /**
   * Constructs the feed client.
   */
  public function __construct(private readonly ClientInterface $httpClient, private readonly FeedNormalizer $normalizer) {}

  /**
   * Fetches and validates all records before any queue writes occur.
   */
  public function fetch(string $url): array {
    $schemes = ['http', 'https'];
    if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), $schemes, TRUE) || parse_url($url, PHP_URL_USER) !== NULL || parse_url($url, PHP_URL_PASS) !== NULL) {
      throw new \InvalidArgumentException('Use an HTTP(S) feed URL without credentials.');
    }
    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 10,
        'read_timeout' => 10,
        'connect_timeout' => 5,
        'allow_redirects' => FALSE,
        'stream' => TRUE,
        'headers' => ['Accept' => 'application/json'],
      ]);
      $stream = $response->getBody();
      try {
        if ($response->getStatusCode() !== 200) {
          throw new \RuntimeException('The feed did not return HTTP 200.');
        }
        $body = '';
        while (!$stream->eof() && strlen($body) <= 1048576) {
          $chunk = $stream->read(min(8192, 1048577 - strlen($body)));
          if ($chunk === '' && !$stream->eof()) {
            throw new \RuntimeException('The feed stream stalled.');
          }
          $body .= $chunk;
        }
        if (strlen($body) > 1048576) {
          throw new \RuntimeException('The feed exceeds 1 MiB.');
        }
      }
      finally {
        $stream->close();
      }
      $feed = json_decode($body, TRUE, 64, JSON_THROW_ON_ERROR);
      if (!is_array($feed)) {
        throw new \InvalidArgumentException('Expected a JSON object.');
      }
      return $this->normalizer->normalize($feed);
    }
    catch (GuzzleException | \JsonException $exception) {
      // Avoid logging URLs or response bodies, which can contain private data.
      throw new \RuntimeException('Unable to read a valid public JSON feed.', 0, $exception);
    }
  }

}
