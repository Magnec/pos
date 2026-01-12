<?php

namespace Drupal\pos\Traits;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * POS API Security Trait.
 * 
 * 3 Katmanlı Güvenlik:
 * 1. Permission (Drupal izinleri)
 * 2. Session Token (CSRF koruması)
 * 3. Rate Limiting (Brute force koruması)
 */
trait PosApiSecurityTrait {

  /**
   * API erişim kontrolü.
   * 
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   HTTP request.
   * @param bool $require_token
   *   Token kontrolü yapılsın mı? (POST işlemler için)
   * 
   * @return \Symfony\Component\HttpFoundation\JsonResponse|null
   *   Hata varsa JsonResponse, yoksa NULL.
   */
  protected function checkApiAccess(Request $request, $require_token = FALSE) {
    // 1. Permission kontrolü
    $current_user = \Drupal::currentUser();
    
    if (!$current_user->hasPermission('access pos system')) {
      \Drupal::logger('pos_security')->warning('Yetkisiz API erişimi: @ip - @path', [
        '@ip' => $request->getClientIp(),
        '@path' => $request->getPathInfo(),
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Yetkisiz erişim',
      ], 403);
    }
    
    // 2. Token kontrolü (POST istekler için)
    if ($require_token) {
      $token_error = $this->validateToken($request);
      if ($token_error) {
        return $token_error;
      }
    }
    
    // 3. Rate Limiting
    $rate_limit_error = $this->checkRateLimit($request);
    if ($rate_limit_error) {
      return $rate_limit_error;
    }
    
    return NULL;
  }
  
  /**
   * Token doğrulama.
   */
  protected function validateToken(Request $request) {
    $session = \Drupal::request()->getSession();
    $stored_token = $session->get('pos_token');
    $request_token = $request->headers->get('X-POS-Token');
    
    if (!$stored_token || $stored_token !== $request_token) {
      \Drupal::logger('pos_security')->warning('Geçersiz token: @ip - @path', [
        '@ip' => $request->getClientIp(),
        '@path' => $request->getPathInfo(),
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Geçersiz token',
      ], 403);
    }
    
    return NULL;
  }
  
  /**
   * Rate limiting kontrolü.
   * 
   * Her IP için:
   * - 100 istek / dakika
   * - 1000 istek / saat
   */
  protected function checkRateLimit(Request $request) {
    $ip = $request->getClientIp();
    $path = $request->getPathInfo();
    
    // Rate limit key
    $key_minute = 'pos_rate_limit:' . $ip . ':minute';
    $key_hour = 'pos_rate_limit:' . $ip . ':hour';
    
    // Cache'den oku
    $cache = \Drupal::cache();
    
    // Dakikalık limit
    $minute_data = $cache->get($key_minute);
    $minute_count = $minute_data ? $minute_data->data : 0;
    
    if ($minute_count >= 100) {
      \Drupal::logger('pos_security')->warning('Rate limit aşıldı (dakika): @ip - @count istek', [
        '@ip' => $ip,
        '@count' => $minute_count,
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Çok fazla istek. Lütfen bekleyin.',
        'retry_after' => 60,
      ], 429);
    }
    
    // Saatlik limit
    $hour_data = $cache->get($key_hour);
    $hour_count = $hour_data ? $hour_data->data : 0;
    
    if ($hour_count >= 1000) {
      \Drupal::logger('pos_security')->warning('Rate limit aşıldı (saat): @ip - @count istek', [
        '@ip' => $ip,
        '@count' => $hour_count,
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Saatlik limit aşıldı. Lütfen daha sonra tekrar deneyin.',
        'retry_after' => 3600,
      ], 429);
    }
    
    // Counter'ı artır
    $cache->set($key_minute, $minute_count + 1, time() + 60);
    $cache->set($key_hour, $hour_count + 1, time() + 3600);
    
    return NULL;
  }
  
  /**
   * IP Whitelist kontrolü.
   * 
   * Sadece belirli IP'lerden erişime izin ver.
   */
  protected function checkIpWhitelist(Request $request) {
    $config = \Drupal::config('pos.settings');
    $whitelist = $config->get('ip_whitelist') ?? [];
    
    // Whitelist boşsa tüm IP'lere izin ver
    if (empty($whitelist)) {
      return NULL;
    }
    
    $client_ip = $request->getClientIp();
    
    // IP whitelist'te mi?
    if (!in_array($client_ip, $whitelist)) {
      \Drupal::logger('pos_security')->warning('IP whitelist reddedildi: @ip', [
        '@ip' => $client_ip,
      ]);
      
      return new JsonResponse([
        'success' => FALSE,
        'error' => 'Erişim engellenmiştir',
      ], 403);
    }
    
    return NULL;
  }

}
