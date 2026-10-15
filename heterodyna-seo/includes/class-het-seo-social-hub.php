<?php
if (!defined('ABSPATH')) { exit; }

class HET_SEO_Social_Hub {

	/**
	 * Simple at-rest encryption for tokens stored in settings_json.
	 * Not a full secrets manager, but better than plain text.
	 */
    private static function enc_key(): string {
        $salt = defined('AUTH_SALT') ? (string)AUTH_SALT : 'het_seo_social_fallback_salt';
        return hash('sha256', $salt . '::het-seo-social', true); // binary key
    }

    private static function enc($plain): string {
        $plain = (string)$plain;
        if ($plain === '') { return ''; }
        $key = self::enc_key();
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) { return ''; }
        return 'enc:' . base64_encode($iv . $cipher);
    }

    private static function dec($ciphertext): string {
        $ciphertext = (string)$ciphertext;
        if ($ciphertext === '') { return ''; }
        if (strpos($ciphertext, 'enc:') !== 0) { return $ciphertext; }
        $raw = base64_decode(substr($ciphertext, 4), true);
        if ($raw === false || strlen($raw) < 17) { return ''; }
        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key = self::enc_key();
        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return ($plain === false) ? '' : (string)$plain;
    }

    public static function get_account($id): ?array {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_accounts';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE id=%d", (int)$id), ARRAY_A);
        return $row ? $row : null;
    }

    public static function update_account_settings($id, $settings = []): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_accounts';
        $now = current_time('mysql');
        $settings = is_array($settings) ? $settings : [];
        $ok = (false !== $wpdb->update($t, [
            'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ], ['id' => (int)$id]));
        if ($ok) {
            self::log('info', 'Account settings updated', ['account_id'=>(int)$id]);
        }
        return (bool)$ok;
    }

    public static function get_account_setting($account, $key, $default = ''): string {
        $settings = [];
        if (is_array($account) && !empty($account['settings_json'])) {
            $settings = json_decode((string)$account['settings_json'], true);
            if (!is_array($settings)) { $settings = []; }
        }
        $v = $settings[$key] ?? $default;
        $v = is_string($v) ? $v : $default;
        // decrypt if needed
        if (in_array($key, ['x_access_token','x_refresh_token','li_access_token','li_refresh_token'], true)) {
            return self::dec($v);
        }
        return (string)$v;
    }

    public static function set_account_setting(&$settings, $key, $value): void {
        if (!is_array($settings)) { $settings = []; }
        $value = (string)$value;
        if (in_array($key, ['x_access_token','x_refresh_token','li_access_token','li_refresh_token'], true)) {
            $settings[$key] = self::enc($value);
        } else {
            $settings[$key] = $value;
        }
    }

    /**
     * Publish a text post via LinkedIn UGC API.
     * Requires: access token (w_member_social or w_organization_social) and author URN.
     * Author URN examples:
     *  - person: "urn:li:person:xxxxxxxx"
     *  - organization: "urn:li:organization:xxxxxxxx"
     */
    public static function li_publish_post($access_token, $author_urn, $text, $visibility = 'PUBLIC'): array {
        $access_token = trim((string)$access_token);
        $author_urn = trim((string)$author_urn);
        $text = trim((string)$text);
        $visibility = strtoupper(trim((string)$visibility));
        if ($visibility === '') { $visibility = 'PUBLIC'; }

        if ($access_token === '' || $author_urn === '' || $text === '') {
            return ['ok'=>false,'err'=>'Brak tokena, autora (URN) lub treści.'];
        }

        $endpoint = 'https://api.linkedin.com/v2/ugcPosts';
        $payload = [
            'author' => $author_urn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [ 'text' => $text ],
                    'shareMediaCategory' => 'NONE',
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ];

        $resp = wp_remote_post($endpoint, [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($resp)) {
            return ['ok'=>false,'err'=>$resp->get_error_message()];
        }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        if ($code >= 200 && $code < 300) {
            // LinkedIn often returns empty body + Location header.
            $location = wp_remote_retrieve_header($resp, 'location');
            return ['ok'=>true,'code'=>$code,'json'=>$json,'location'=>$location];
        }
        $msg = '';
        if (is_array($json) && !empty($json['message'])) {
            $msg = (string)$json['message'];
        } elseif ($body !== '') {
            $msg = wp_strip_all_tags($body);
        }
        if ($msg === '') { $msg = 'LinkedIn API error'; }
        return ['ok'=>false,'code'=>$code,'err'=>$msg,'body'=>$body,'json'=>$json];
    }

    /**
     * Publish a LinkedIn post with a single URL attachment (ARTICLE).
     * Pragmatic "media" support: LinkedIn UGC supports attaching a URL as ARTICLE
     * without uploading binary assets.
     */
    public static function li_publish_post_with_url($access_token, $author_urn, $text, $url, $visibility = 'PUBLIC'): array {
        $access_token = trim((string)$access_token);
        $author_urn = trim((string)$author_urn);
        $text = trim((string)$text);
        $url = trim((string)$url);
        $visibility = strtoupper(trim((string)$visibility));
        if ($visibility === '') { $visibility = 'PUBLIC'; }

        if ($access_token === '' || $author_urn === '' || $text === '' || $url === '') {
            return ['ok'=>false,'err'=>'Brak tokena, autora (URN), treści lub URL.'];
        }

        $endpoint = 'https://api.linkedin.com/v2/ugcPosts';
        $payload = [
            'author' => $author_urn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [ 'text' => $text ],
                    'shareMediaCategory' => 'ARTICLE',
                    'media' => [[
                        'status' => 'READY',
                        'originalUrl' => $url,
                        'title' => [ 'text' => mb_substr($text, 0, 120) ],
                    ]],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ];

        $resp = wp_remote_post($endpoint, [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($resp)) {
            return ['ok'=>false,'err'=>$resp->get_error_message()];
        }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        if ($code >= 200 && $code < 300) {
            $location = wp_remote_retrieve_header($resp, 'location');
            return ['ok'=>true,'code'=>$code,'json'=>$json,'location'=>$location];
        }
        $msg = '';
        if (is_array($json) && !empty($json['message'])) {
            $msg = (string)$json['message'];
        } elseif ($body !== '') {
            $msg = wp_strip_all_tags($body);
        }
        if ($msg === '') { $msg = 'LinkedIn API error'; }
        return ['ok'=>false,'code'=>$code,'err'=>$msg,'body'=>$body,'json'=>$json];
    }

    

    // ===== Media helpers (03.06.026) =====

    /**
     * Resolve a media entry (attachment_id or url) to a local file path + mime.
     * Returns: ['ok'=>bool,'path'=>string,'mime'=>string,'size'=>int,'url'=>string,'kind'=>'attachment|url','err'=>string]
     */
    private static function resolve_media_item($m): array {
        $out = ['ok'=>false,'path'=>'','mime'=>'','size'=>0,'url'=>'','kind'=>'','err'=>''];
        if (!is_array($m)) { $out['err'] = 'media_not_array'; return $out; }

        $aid = isset($m['attachment_id']) ? (int)$m['attachment_id'] : 0;
        $url = isset($m['url']) ? trim((string)$m['url']) : '';

        if ($aid > 0) {
            $path = get_attached_file($aid);
            if (!$path || !file_exists($path)) { $out['err']='attachment_missing'; return $out; }
            $mime = function_exists('mime_content_type') ? (string)@mime_content_type($path) : '';
            if ($mime === '') {
                $ft = wp_check_filetype($path);
                $mime = isset($ft['type']) ? (string)$ft['type'] : '';
            }
            $out['ok'] = true;
            $out['path'] = (string)$path;
            $out['mime'] = $mime ?: 'application/octet-stream';
            $out['size'] = (int)@filesize($path);
            $out['url'] = wp_get_attachment_url($aid) ?: '';
            $out['kind'] = 'attachment';
            return $out;
        }

        if ($url !== '') {
            $tmp = self::download_to_tmp($url);
            if (empty($tmp['ok'])) { return $tmp; }
            $out = $tmp;
            $out['kind'] = 'url';
            return $out;
        }

        $out['err'] = 'empty_media';
        return $out;
    }

    /**
     * Download remote URL to a temporary file (best-effort).
     */
    private static function download_to_tmp(string $url): array {
        $url = trim($url);
        $out = ['ok'=>false,'path'=>'','mime'=>'','size'=>0,'url'=>$url,'kind'=>'url','err'=>''];
        if ($url === '') { $out['err']='empty_url'; return $out; }

        $resp = wp_remote_get($url, ['timeout'=>25, 'redirection'=>3]);
        if (is_wp_error($resp)) { $out['err']=$resp->get_error_message(); return $out; }
        $code = (int)wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) { $out['err']='http_' . $code; return $out; }

        $body = wp_remote_retrieve_body($resp);
        if ($body === '' || $body === null) { $out['err']='empty_body'; return $out; }

        $ct = (string)wp_remote_retrieve_header($resp, 'content-type');
        $mime = trim(explode(';', $ct)[0]) ?: 'application/octet-stream';

        $tmp = wp_tempnam('het-seo-media');
        if (!$tmp) { $out['err']='tempnam_failed'; return $out; }
        file_put_contents($tmp, $body);

        $out['ok'] = true;
        $out['path'] = $tmp;
        $out['mime'] = $mime;
        $out['size'] = (int)@filesize($tmp);
        return $out;
    }

    private static function is_image_mime(string $mime): bool {
        $mime = strtolower(trim($mime));
        return in_array($mime, ['image/jpeg','image/jpg','image/png','image/gif','image/webp'], true);
    }

    private static function is_video_mime(string $mime): bool {
        $mime = strtolower(trim($mime));
        return in_array($mime, ['video/mp4','video/quicktime','video/webm'], true);
    }

    // ===== LinkedIn Assets upload (image) =====

    /**
     * Upload an image to LinkedIn Assets API and return asset URN.
     * Uses registerUpload + PUT uploadUrl.
     *
     * Docs: LinkedIn Assets registerUpload flow.
     */
    public static function li_upload_image_asset(string $access_token, string $owner_urn, string $file_path, string $mime = 'image/jpeg'): array {
        $access_token = trim($access_token);
        $owner_urn = trim($owner_urn);
        $file_path = (string)$file_path;
        $mime = $mime ?: 'image/jpeg';

        if ($access_token === '' || $owner_urn === '' || $file_path === '' || !file_exists($file_path)) {
            return ['ok'=>false,'err'=>'Brak tokena/owner lub pliku.'];
        }

        // 1) registerUpload
        $endpoint = 'https://api.linkedin.com/v2/assets?action=registerUpload';
        $payload = [
            'registerUploadRequest' => [
                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                'owner' => $owner_urn,
                'serviceRelationships' => [[
                    'relationshipType' => 'OWNER',
                    'identifier' => 'urn:li:userGeneratedContent',
                ]],
            ],
        ];

        $resp = wp_remote_post($endpoint, [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        if (is_wp_error($resp)) { return ['ok'=>false,'err'=>$resp->get_error_message()]; }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            $msg = is_array($json) && !empty($json['message']) ? (string)$json['message'] : (wp_strip_all_tags($body) ?: ('HTTP ' . $code));
            return ['ok'=>false,'code'=>$code,'err'=>$msg,'raw'=>$body,'json'=>$json];
        }

        $asset = (string)($json['value']['asset'] ?? '');
        $uploadUrl = (string)($json['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'] ?? '');

        if ($asset === '' || $uploadUrl === '') {
            return ['ok'=>false,'err'=>'LinkedIn registerUpload: brak asset/uploadUrl','json'=>$json];
        }

        // 2) PUT uploadUrl with binary
        $bin = file_get_contents($file_path);
        if ($bin === false || $bin === '') { return ['ok'=>false,'err'=>'Nie można odczytać pliku.']; }

        $put = wp_remote_request($uploadUrl, [
            'method' => 'PUT',
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => $mime,
            ],
            'body' => $bin,
        ]);

        if (is_wp_error($put)) { return ['ok'=>false,'err'=>$put->get_error_message()]; }
        $pcode = (int)wp_remote_retrieve_response_code($put);
        if ($pcode < 200 || $pcode >= 300) {
            $pbody = (string)wp_remote_retrieve_body($put);
            return ['ok'=>false,'code'=>$pcode,'err'=>'LinkedIn upload failed','raw'=>$pbody];
        }

        return ['ok'=>true,'asset'=>$asset,'uploadUrl'=>$uploadUrl];
    }

    /**
     * Publish LinkedIn UGC post with a single IMAGE asset (uploaded via assets).
     */
    public static function li_publish_post_with_image_asset(string $access_token, string $author_urn, string $text, string $asset_urn, string $visibility='PUBLIC'): array {
        $access_token = trim($access_token);
        $author_urn = trim($author_urn);
        $text = trim($text);
        $asset_urn = trim($asset_urn);
        $visibility = strtoupper(trim($visibility));
        if ($visibility === '') { $visibility = 'PUBLIC'; }

        if ($access_token === '' || $author_urn === '' || $text === '' || $asset_urn === '') {
            return ['ok'=>false,'err'=>'Brak tokena, autora, treści lub asset.'];
        }

        $endpoint = 'https://api.linkedin.com/v2/ugcPosts';
        $payload = [
            'author' => $author_urn,
            'lifecycleState' => 'PUBLISHED',
            'specificContent' => [
                'com.linkedin.ugc.ShareContent' => [
                    'shareCommentary' => [ 'text' => $text ],
                    'shareMediaCategory' => 'IMAGE',
                    'media' => [[
                        'status' => 'READY',
                        'media' => $asset_urn,
                        'title' => [ 'text' => mb_substr($text, 0, 120) ],
                    ]],
                ],
            ],
            'visibility' => [
                'com.linkedin.ugc.MemberNetworkVisibility' => $visibility,
            ],
        ];

        $resp = wp_remote_post($endpoint, [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Restli-Protocol-Version' => '2.0.0',
            ],
            'body' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($resp)) { return ['ok'=>false,'err'=>$resp->get_error_message()]; }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        if ($code >= 200 && $code < 300) {
            $location = wp_remote_retrieve_header($resp, 'location');
            return ['ok'=>true,'code'=>$code,'json'=>$json,'location'=>$location];
        }
        $msg = '';
        if (is_array($json) && !empty($json['message'])) {
            $msg = (string)$json['message'];
        } elseif ($body !== '') {
            $msg = wp_strip_all_tags($body);
        }
        if ($msg === '') { $msg = 'LinkedIn API error'; }
        return ['ok'=>false,'code'=>$code,'err'=>$msg,'body'=>$body,'json'=>$json];
    }

    // ===== X (Twitter) v2 chunked media upload (OAuth2 user token) =====

    private static function http_post_json(string $url, array $headers, array $data, int $timeout = 25): array {
        $resp = wp_remote_post($url, [
            'timeout' => $timeout,
            'headers' => array_merge(['Content-Type'=>'application/json; charset=utf-8'], $headers),
            'body' => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);
        if (is_wp_error($resp)) { return ['ok'=>false,'err'=>$resp->get_error_message()]; }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        if ($code >= 200 && $code < 300) { return ['ok'=>true,'code'=>$code,'json'=>$json,'body'=>$body]; }
        $msg = is_array($json) && !empty($json['detail']) ? (string)$json['detail'] : (wp_strip_all_tags($body) ?: ('HTTP ' . $code));
        return ['ok'=>false,'code'=>$code,'err'=>$msg,'json'=>$json,'body'=>$body];
    }

    /**
     * Upload media to X via v2 endpoints: initialize -> append (chunks) -> finalize -> optional status poll.
     * Returns: ['ok'=>true,'media_id'=>string] or error.
     */
    public static function x_upload_media_chunked(string $access_token, string $file_path, string $mime, string $category = 'tweet_image'): array {
        $access_token = trim($access_token);
        if ($access_token === '' || $file_path === '' || !file_exists($file_path)) {
            return ['ok'=>false,'err'=>'Brak tokena lub pliku.'];
        }
        $mime = $mime ?: 'application/octet-stream';
        $size = (int)@filesize($file_path);
        if ($size <= 0) { return ['ok'=>false,'err'=>'Nieprawidłowy rozmiar pliku.']; }

        $headers = ['Authorization' => 'Bearer ' . $access_token];

        // 1) initialize
        $init = self::http_post_json('https://api.x.com/2/media/upload/initialize', $headers, [
            'media_category' => $category,
            'media_type' => $mime,
            'total_bytes' => $size,
            'shared' => true,
        ], 25);

        if (empty($init['ok']) || !is_array($init['json'])) {
            return ['ok'=>false,'err'=>'X INIT failed','res'=>$init];
        }
        $media_id = (string)($init['json']['data']['id'] ?? '');
        if ($media_id === '') { return ['ok'=>false,'err'=>'X INIT: missing media id','res'=>$init]; }

        // 2) append chunks (multipart/form-data)
        $chunk_size = 1024 * 1024; // 1MB
        $fh = fopen($file_path, 'rb');
        if (!$fh) { return ['ok'=>false,'err'=>'Cannot open file']; }

        $segment = 0;
        while (!feof($fh)) {
            $chunk = fread($fh, $chunk_size);
            if ($chunk === false) { fclose($fh); return ['ok'=>false,'err'=>'Read chunk failed']; }
            if ($chunk === '') { break; }

            $tmp = wp_tempnam('het-seo-x-chunk');
            if (!$tmp) { fclose($fh); return ['ok'=>false,'err'=>'tempnam_failed']; }
            file_put_contents($tmp, $chunk);

            // Use cURL for multipart to be reliable
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.x.com/2/media/upload/' . rawurlencode($media_id) . '/append');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $access_token]);
                $post = [
                    'segment_index' => (string)$segment,
                    'media' => curl_file_create($tmp, $mime, basename($file_path)),
                ];
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                $resp_body = curl_exec($ch);
                $curl_err = curl_error($ch);
                $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                @unlink($tmp);

                if ($resp_body === false || $http_code < 200 || $http_code >= 300) {
                    fclose($fh);
                    return ['ok'=>false,'err'=>'X APPEND failed', 'code'=>$http_code, 'curl_err'=>$curl_err, 'body'=>$resp_body];
                }
            } else {
                @unlink($tmp);
                fclose($fh);
                return ['ok'=>false,'err'=>'cURL missing for X multipart upload'];
            }

            $segment++;
        }
        fclose($fh);

        // 3) finalize
        $fin = self::http_post_json('https://api.x.com/2/media/upload/' . rawurlencode($media_id) . '/finalize', $headers, [], 25);
        if (empty($fin['ok'])) {
            return ['ok'=>false,'err'=>'X FINALIZE failed','res'=>$fin,'media_id'=>$media_id];
        }

        // 4) status (optional) — if processing_info requires polling
        $state = (string)($fin['json']['data']['processing_info']['state'] ?? '');
        if ($state !== '' && $state !== 'succeeded') {
            $max_wait = 30; // seconds
            $start = time();
            do {
                $check_after = (int)($fin['json']['data']['processing_info']['check_after_secs'] ?? 2);
                if ($check_after <= 0) { $check_after = 2; }
                sleep(min(5, $check_after));

                $st = wp_remote_get(add_query_arg(['command'=>'STATUS','media_id'=>$media_id], 'https://api.x.com/2/media/upload'), [
                    'timeout'=>25,
                    'headers'=>$headers,
                ]);
                if (is_wp_error($st)) { break; }
                $scode = (int)wp_remote_retrieve_response_code($st);
                $sbody = (string)wp_remote_retrieve_body($st);
                $sjson = json_decode($sbody, true);
                if ($scode >= 200 && $scode < 300 && is_array($sjson)) {
                    $state = (string)($sjson['data']['processing_info']['state'] ?? '');
                    if ($state === 'succeeded') { break; }
                    if ($state === 'failed') {
                        return ['ok'=>false,'err'=>'X processing failed','media_id'=>$media_id,'status'=>$sjson];
                    }
                    $fin['json'] = $sjson;
                } else {
                    break;
                }
            } while (time() - $start < $max_wait);
        }

        return ['ok'=>true,'media_id'=>$media_id, 'res'=>$fin];
    }

/**
     * Publish a tweet via X API using OAuth2 user access token (Bearer).
     * Requires an access token with tweet.write scope.
     */
    public static function x_publish_tweet($access_token, $text, $media_ids = []): array {
        $access_token = trim((string)$access_token);
        $text = trim((string)$text);
        if ($access_token === '' || $text === '') {
            return ['ok'=>false,'err'=>'Brak tokena lub treści.'];
        }

        
        $media_ids = is_array($media_ids) ? array_values(array_filter(array_map('strval', $media_ids))) : [];

        $endpoint = 'https://api.twitter.com/2/tweets';
        $resp = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body' => wp_json_encode(self::x_build_tweet_payload($text, $media_ids), JSON_UNESCAPED_UNICODE),
        ]);

        if (is_wp_error($resp)) {
            return ['ok'=>false,'err'=>$resp->get_error_message()];
        }

        $code = (int)wp_remote_retrieve_response_code($resp);
        $body = (string)wp_remote_retrieve_body($resp);
        $json = json_decode($body, true);
        if ($code >= 200 && $code < 300) {
            return ['ok'=>true,'code'=>$code,'json'=>$json];
        }
        $msg = '';
        if (is_array($json) && !empty($json['detail'])) {
            $msg = (string)$json['detail'];
        } elseif (is_array($json) && !empty($json['errors'][0]['message'])) {
            $msg = (string)$json['errors'][0]['message'];
        }
        if ($msg === '') { $msg = 'HTTP ' . $code; }
        return ['ok'=>false,'code'=>$code,'err'=>$msg,'raw'=>$body];
    }


    private static function x_build_tweet_payload(string $text, $media_ids = []): array {
        $payload = ['text' => $text];
        $media_ids = is_array($media_ids) ? array_values(array_filter(array_map('strval', $media_ids))) : [];
        if (!empty($media_ids)) {
            $payload['media'] = ['media_ids' => array_slice($media_ids, 0, 4)];
        }
        return $payload;
    }


    public static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        $t_accounts  = $wpdb->prefix . 'het_seo_social_accounts';
        $t_posts     = $wpdb->prefix . 'het_seo_social_posts';
        $t_campaigns = $wpdb->prefix . 'het_seo_social_campaigns';
        $t_queue     = $wpdb->prefix . 'het_seo_social_queue';
        $t_logs      = $wpdb->prefix . 'het_seo_social_logs';
        $t_metrics   = $wpdb->prefix . 'het_seo_social_metrics';

        $sql = [];

        $sql[] = "CREATE TABLE $t_accounts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            provider VARCHAR(64) NOT NULL,
            account_label VARCHAR(190) NOT NULL,
            account_ref VARCHAR(190) NULL,
            settings_json LONGTEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY workspace_id (workspace_id),
            KEY provider (provider),
            KEY is_enabled (is_enabled)
        ) $charset;";

        $sql[] = "CREATE TABLE $t_posts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(190) NOT NULL,
            content LONGTEXT NOT NULL,
            media_json LONGTEXT NULL,
            tags_json LONGTEXT NULL,
            source_url VARCHAR(255) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY workspace_id (workspace_id),
            KEY status (status)
        ) $charset;";

        $sql[] = "CREATE TABLE $t_campaigns (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            post_id BIGINT UNSIGNED NULL,
            targets_json LONGTEXT NULL,
            schedule_at DATETIME NULL,
            mode VARCHAR(16) NOT NULL DEFAULT 'dry',
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY workspace_id (workspace_id),
            KEY status (status),

            KEY workspace_id (workspace_id),
            KEY schedule_at (schedule_at)
        ) $charset;";

        $sql[] = "CREATE TABLE $t_queue (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            campaign_id BIGINT UNSIGNED NOT NULL,
            account_id BIGINT UNSIGNED NOT NULL,
            post_id BIGINT UNSIGNED NULL,

            status VARCHAR(32) NOT NULL DEFAULT 'queued',
            attempt SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            max_attempts SMALLINT UNSIGNED NOT NULL DEFAULT 5,

            next_run_at DATETIME NULL,
            locked_at DATETIME NULL,
            locked_by VARCHAR(64) NULL,

            dedupe_key CHAR(64) NULL,
            external_ref VARCHAR(255) NULL,

            last_error TEXT NULL,
            last_response_json LONGTEXT NULL,
            payload_json LONGTEXT NULL,

            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),

            KEY status (status),
            KEY next_run_at (next_run_at),
            KEY campaign_id (campaign_id),
            KEY account_id (account_id),
            KEY dedupe_key (dedupe_key)
        ) $charset;";

        $sql[] = "CREATE TABLE $t_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(16) NOT NULL DEFAULT 'info',
            message TEXT NOT NULL,
            context_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset;";

        $sql[] = "CREATE TABLE $t_metrics (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            scope VARCHAR(32) NOT NULL DEFAULT 'site',
            ref_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            date_ymd CHAR(10) NOT NULL,

            gsc_clicks BIGINT NULL,
            gsc_impressions BIGINT NULL,
            gsc_ctr DECIMAL(7,3) NULL,
            gsc_position DECIMAL(7,3) NULL,

            ga_sessions BIGINT NULL,
            ga_users BIGINT NULL,

            meta_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,

            PRIMARY KEY (id),
            UNIQUE KEY uniq_scope_ref_date (workspace_id, scope, ref_id, date_ymd),
            KEY workspace_id (workspace_id),
            KEY date_ymd (date_ymd)
        ) $charset;";


        // ===== Enterprise/Agency (03.06.029) =====
        $sql[] = "CREATE TABLE $t_workspaces (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            settings_json LONGTEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_enabled (is_enabled)
        ) $charset;";

        $sql[] = "CREATE TABLE $t_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(32) NOT NULL DEFAULT 'editor',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_ws_user (workspace_id, user_id),
            KEY role (role),
            KEY workspace_id (workspace_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $t_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            label VARCHAR(190) NOT NULL,
            token_hash CHAR(64) NOT NULL,
            scopes_json LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            last_used_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY workspace_id (workspace_id)
        ) $charset;";

        $sql[] = "CREATE TABLE $t_webhooks (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            workspace_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            url VARCHAR(255) NOT NULL,
            secret VARCHAR(190) NULL,
            events_json LONGTEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            last_delivery_at DATETIME NULL,
            last_status SMALLINT NULL,
            last_error TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY workspace_id (workspace_id),
            KEY is_enabled (is_enabled)
        ) $charset;";
        foreach ($sql as $q) { dbDelta($q); }
    }

    public static function ensure_cron() {
        // 03.06.027: faster tick + custom schedule (5 minutes) for social queue.
        add_filter('cron_schedules', function($s) {
            if (!is_array($s)) { $s = []; }
            if (!isset($s['het_5min'])) {
                $s['het_5min'] = [
                    'interval' => 300,
                    'display'  => 'Heterodyna Social Queue (5 min)',
                ];
            }
            return $s;
        });

        if (!wp_next_scheduled('het_seo_social_queue_tick')) {
            wp_schedule_event(time() + 120, 'het_5min', 'het_seo_social_queue_tick');
        }

        // 03.06.028: daily metrics snapshot (Site Kit KPIs)
        if (!wp_next_scheduled('het_seo_social_metrics_tick')) {
            // Start soon, then daily.
            wp_schedule_event(time() + 180, 'daily', 'het_seo_social_metrics_tick');
        }
    }
// ===== Metrics (03.06.028): Site Kit KPIs snapshots =====

public static function metrics_latest(string $scope = 'site', int $ref_id = 0): ?array {
    global $wpdb;
    $t = $wpdb->prefix . 'het_seo_social_metrics';
    $scope = sanitize_key($scope);
    $ref_id = (int)$ref_id;
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $t WHERE scope=%s AND ref_id=%d ORDER BY date_ymd DESC, id DESC LIMIT 1",
        $scope, $ref_id
    ), ARRAY_A);
    return $row ? $row : null;
}

public static function metrics_snapshot(string $scope = 'site', int $ref_id = 0, int $days = 28): array {
    global $wpdb;
    $t = $wpdb->prefix . 'het_seo_social_metrics';
    $scope = sanitize_key($scope);
    $ref_id = (int)$ref_id;
    $days = max(1, min(180, (int)$days));

    $k = class_exists('HET_SEO_SiteKit') ? HET_SEO_SiteKit::get_kpis($days) : ['ok'=>false,'message'=>'No Site Kit','gsc'=>[],'ga4'=>[],'psi'=>[]];
    $date = gmdate('Y-m-d');
    $now = current_time('mysql');

    $row = [
        'scope' => $scope,
        'ref_id' => $ref_id,
        'date_ymd' => $date,
        'gsc_clicks' => isset($k['gsc']['clicks']) ? (int)$k['gsc']['clicks'] : null,
        'gsc_impressions' => isset($k['gsc']['impressions']) ? (int)$k['gsc']['impressions'] : null,
        'gsc_ctr' => isset($k['gsc']['ctr']) ? (float)$k['gsc']['ctr'] : null,
        'gsc_position' => isset($k['gsc']['position']) ? (float)$k['gsc']['position'] : null,
        'ga_sessions' => isset($k['ga4']['sessions']) ? (int)$k['ga4']['sessions'] : null,
        'ga_users' => isset($k['ga4']['users']) ? (int)$k['ga4']['users'] : null,
        'meta_json' => wp_json_encode(['ok'=>$k['ok'] ?? false,'message'=>$k['message'] ?? '', 'psi'=>$k['psi'] ?? []], JSON_UNESCAPED_UNICODE),
        'created_at' => $now,
    ];

    // replace = upsert on UNIQUE(scope,ref_id,date_ymd)
    $wpdb->replace($t, $row);
    return $row;
}

public static function metrics_tick(): void {
    try {
        $row = self::metrics_snapshot('site', 0, 28);
        self::log('info', 'Metrics snapshot saved', ['scope'=>'site','date'=>$row['date_ymd'] ?? null]);
    } catch (Throwable $e) {
        self::log('error', 'Metrics snapshot failed', ['err'=>$e->getMessage()]);
    }
}

    public static function log($level, $message, $context = []) {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_logs';
        $now = current_time('mysql');
        $wpdb->insert($t, [
            'level' => substr((string)$level, 0, 16),
            'message' => (string)$message,
            'context_json' => wp_json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
        ]);
    }

    
    public static function get_socialmedia_templates() : array {
        $sm = get_option('het_seo_socialmedia', []);
        $tpl = (is_array($sm) && isset($sm['templates']) && is_array($sm['templates'])) ? $sm['templates'] : [];
        return is_array($tpl) ? $tpl : [];
    }

    /**
     * Apply per-provider templates (prefix/suffix/max/emoji) before publish.
     * Returns array: ['text'=>string,'changed'=>bool,'note'=>string]
     */
    public static function format_text_for_provider($provider, $text, $templates = null) : array {
        $provider = sanitize_key((string)$provider);
        $orig = (string)$text;
        $templates = is_array($templates) ? $templates : self::get_socialmedia_templates();
        $t = (isset($templates[$provider]) && is_array($templates[$provider])) ? $templates[$provider] : [];

        $prefix = isset($t['prefix']) ? trim((string)$t['prefix']) : '';
        $suffix = isset($t['suffix']) ? trim((string)$t['suffix']) : '';
        $max = isset($t['max']) ? (int)$t['max'] : 0;
        if ($max <= 0) {
            $max = ($provider === 'x') ? 280 : (($provider === 'linkedin') ? 3000 : 5000);
        }
        $max = max(10, min(10000, $max));

        $emoji = isset($t['emoji']) ? sanitize_key((string)$t['emoji']) : 'auto';
        if (!in_array($emoji, ['off','auto','light','bold'], true)) { $emoji = 'auto'; }

        $out = trim((string)$text);
        if ($prefix !== '') {
            $out = $prefix . ($out !== '' ? "\n\n" . $out : '');
        }
        if ($suffix !== '') {
            $out = $out . ($out !== '' ? "\n\n" : '') . $suffix;
        }

        if ($emoji === 'off') {
            // Best-effort remove emoji/symbols
            $out = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $out);
            $out = preg_replace('/\s{2,}/', ' ', $out);
            $out = trim($out);
        }

        $note = '';
        if (mb_strlen($out) > $max) {
            $out = mb_substr($out, 0, max(0, $max - 1)) . '…';
            $note = 'trimmed_to_max';
        }

        return [
            'text' => $out,
            'changed' => ($out !== $orig),
            'note' => $note,
        ];
    }

public static function get_providers(): array {
        return [
            'local' => 'Local (dry-run)',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'x' => 'X (Twitter)',
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
            'pinterest' => 'Pinterest',
            'reddit' => 'Reddit',
            'telegram' => 'Telegram',
            'discord' => 'Discord',
            'mastodon' => 'Mastodon',
        ];
    }


    /**
     * Build URL with UTM parameters (safe, keeps existing query).
     * Uses settings from Socialmedia accordion.
     */
    public static function apply_utm_to_url($url, $utm = [], $campaign_name = '', $account = []): string {
        $url = trim((string)$url);
        if ($url === '') { return ''; }
        $utm = is_array($utm) ? $utm : [];

        $source = isset($utm['source']) ? trim((string)$utm['source']) : '';
        $medium = isset($utm['medium']) ? trim((string)$utm['medium']) : '';
        $campaign_mode = isset($utm['campaign_mode']) ? (string)$utm['campaign_mode'] : 'name';
        $campaign_fixed = isset($utm['campaign_fixed']) ? trim((string)$utm['campaign_fixed']) : '';
        $content_mode = isset($utm['content_mode']) ? (string)$utm['content_mode'] : 'provider';
        $term = isset($utm['term']) ? trim((string)$utm['term']) : '';

        $params = [];
        if ($source !== '') { $params['utm_source'] = $source; }
        if ($medium !== '') { $params['utm_medium'] = $medium; }

        $camp = '';
        if ($campaign_mode === 'fixed' && $campaign_fixed !== '') {
            $camp = $campaign_fixed;
        } else {
            $camp = $campaign_name;
        }
        $camp = trim((string)$camp);
        if ($camp !== '') {
            $camp = preg_replace('/\s+/', '_', $camp);
            $params['utm_campaign'] = mb_substr($camp, 0, 120);
        }

        if ($content_mode !== 'off') {
            $content = '';
            if ($content_mode === 'account') {
                $content = isset($account['account_label']) ? (string)$account['account_label'] : '';
            } else {
                $content = isset($account['provider']) ? (string)$account['provider'] : '';
            }
            $content = trim($content);
            if ($content !== '') {
                $content = preg_replace('/\s+/', '_', $content);
                $params['utm_content'] = mb_substr($content, 0, 120);
            }
        }

        if ($term !== '') {
            $term = preg_replace('/\s+/', '_', $term);
            $params['utm_term'] = mb_substr($term, 0, 120);
        }

        if (empty($params)) { return $url; }

        $parts = wp_parse_url($url);
        if (!$parts || empty($parts['host'])) {
            return $url;
        }

        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            if (!is_array($query)) { $query = []; }
        }

        foreach ($params as $k => $v) {
            if ($v === '') { continue; }
            $query[$k] = $v;
        }

        $new_query = http_build_query($query);

        $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = isset($parts['path']) ? $parts['path'] : '';
        $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . '://' . $host . $port . $path . ($new_query ? '?' . $new_query : '') . $frag;
    }

    public static function build_utm_url($url, $campaign_name, $account = null) {
        $url = trim((string)$url);
        if ($url === '') { return ''; }
        $sm = get_option('het_seo_socialmedia', []);
    $pub = self::get_publish_settings();
    $publish_mode = (string)($pub['mode'] ?? 'live');
        $utm = (is_array($sm) && isset($sm['utm']) && is_array($sm['utm'])) ? $sm['utm'] : [];
        $source = isset($utm['source']) ? trim((string)$utm['source']) : '';
        $medium = isset($utm['medium']) ? trim((string)$utm['medium']) : 'social';
        $camp_mode = isset($utm['campaign_mode']) ? (string)$utm['campaign_mode'] : 'name';
        $camp_fixed = isset($utm['campaign_fixed']) ? trim((string)$utm['campaign_fixed']) : '';
        $content_mode = isset($utm['content_mode']) ? (string)$utm['content_mode'] : 'provider';
        $term = isset($utm['term']) ? trim((string)$utm['term']) : '';

        $campaign = ($camp_mode === 'fixed' && $camp_fixed !== '') ? $camp_fixed : (string)$campaign_name;
        $campaign = sanitize_title_with_dashes($campaign);

        // Build params only if at least one is set
        $params = [];
        if ($source !== '') { $params['utm_source'] = $source; }
        if ($medium !== '') { $params['utm_medium'] = $medium; }
        if ($campaign !== '') { $params['utm_campaign'] = $campaign; }
        if ($content_mode !== 'off') {
            if (is_array($account)) {
                if ($content_mode === 'account' && !empty($account['account_label'])) {
                    $params['utm_content'] = sanitize_title_with_dashes((string)$account['account_label']);
                } elseif ($content_mode === 'provider' && !empty($account['provider'])) {
                    $params['utm_content'] = sanitize_title_with_dashes((string)$account['provider']);
                }
            }
        }
        if ($term !== '') { $params['utm_term'] = sanitize_title_with_dashes($term); }

        if (empty($params)) { return $url; }
        foreach ($params as $k=>$v) {
            $url = add_query_arg($k, rawurlencode($v), $url);
        }
        // add_query_arg encodes, but we used rawurlencode to keep safe; normalize double-encoding is acceptable here.
        return $url;
    }

    /**
     * Ensure at least one enabled account exists (for dry-run / local queue usage).
     * Returns account id.
     */
    public static function ensure_default_account(): int {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_accounts';
        $id = (int) $wpdb->get_var("SELECT id FROM {$t} WHERE is_enabled=1 ORDER BY id ASC LIMIT 1");
        if ($id > 0) { return $id; }
        $now = current_time('mysql');
        $wpdb->insert($t, [
            'provider' => 'local',
            'account_label' => 'Local (dry-run)',
            'account_ref' => 'local://dry-run',
            'settings_json' => wp_json_encode(['note'=>'Auto-created for local dry-run queue'], JSON_UNESCAPED_UNICODE),
            'is_enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $new_id = (int) $wpdb->insert_id;
        self::log('info', 'Default local account ensured', ['account_id' => $new_id]);
        return $new_id;
    }



    public static function add_account($provider, $label, $ref = '', $settings = []) {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_accounts';
        $now = current_time('mysql');
        $wpdb->insert($t, [
            'provider' => substr(sanitize_key($provider), 0, 64),
            'account_label' => sanitize_text_field($label),
            'account_ref' => sanitize_text_field($ref),
            'settings_json' => wp_json_encode($settings, JSON_UNESCAPED_UNICODE),
            'is_enabled' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        self::log('info', 'Account added', ['provider'=>$provider,'label'=>$label]);
    }

    public static function list_accounts(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_accounts';
        $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id DESC", ARRAY_A);
        return is_array($rows) ? $rows : [];
    }


    public static function create_post($title, $content, $source_url = '', $media = []): int {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_posts';
        $now = current_time('mysql');
        $media = is_array($media) ? $media : [];
        // normalize: accept array of urls/ids
        $media_norm = [];
        foreach ($media as $m) {
            if (is_array($m)) {
                $url = isset($m['url']) ? trim((string)$m['url']) : '';
                $aid = isset($m['attachment_id']) ? (int)$m['attachment_id'] : 0;
            } else {
                $url = trim((string)$m);
                $aid = 0;
            }
            if ($aid > 0) {
                $media_norm[] = ['attachment_id'=>$aid];
                continue;
            }
            if ($url !== '') {
                $media_norm[] = ['url'=>esc_url_raw($url)];
            }
        }
        $wpdb->insert($t, [
            'title' => sanitize_text_field($title),
            'content' => wp_kses_post($content),
            'media_json' => !empty($media_norm) ? wp_json_encode($media_norm, JSON_UNESCAPED_UNICODE) : null,
            'source_url' => esc_url_raw($source_url),
            'status' => 'draft',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $id = (int)$wpdb->insert_id;
        self::log('info', 'Post created', ['post_id'=>$id]);
        return $id;
    }

    public static function ai_generate($topic, $tone='neutral', $length='medium'): array {
        // Placeholder generator – safe deterministic text. Can be replaced by real AI later.
        $topic = trim((string)$topic);
        if ($topic === '') { $topic = 'Nowy wpis'; }
        $title = mb_substr($topic, 0, 80);
        $body = "🔔 {$topic}\n\n"
              . "• 1 najważniejsza rzecz\n"
              . "• 2 konkretne wskazówki\n"
              . "• 1 call-to-action\n\n"
              . "#SEO #Heterodyna";
        return ['title'=>$title, 'content'=>$body];
    }
/**
 * "Smart" generator (local placeholder) – uses templates + brand voice + guidelines.
 * Deterministic per topic (stable output), but less repetitive than one schema.
 */
public static function ai_generate_smart($topic, $guidelines = '', $voice = [], $variant = 0): array {
    $topic = trim((string)$topic);
    if ($topic === '') { $topic = 'Nowy wpis'; }

    $g = trim((string)$guidelines);
    $voice = is_array($voice) ? $voice : [];

    $tone = isset($voice['tone']) ? trim((string)$voice['tone']) : '';
    $style = isset($voice['style']) ? trim((string)$voice['style']) : 'mixed';
    $cta_raw = isset($voice['cta_list']) ? (string)$voice['cta_list'] : '';
    $hash_raw = isset($voice['hashtags']) ? (string)$voice['hashtags'] : '';
    $emoji_mode = isset($voice['emoji_mode']) ? (string)$voice['emoji_mode'] : 'auto';
    $banned_raw = isset($voice['banned_words']) ? (string)$voice['banned_words'] : '';

    $seed = abs(crc32($topic . '|v' . (string)$variant));

    // normalize lists
    $ctas = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $cta_raw))));
    $hashes = array_values(array_filter(array_map('trim', preg_split('/,|\r\n|\r|\n/', $hash_raw))));
    $banned = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $banned_raw))));

    $templates = [
        'mixed' => [
            function($t) { return "🧠 {$t}\n\nZamiast teorii — konkret:\n1) …\n2) …\n3) …\n\nNa koniec: jedno pytanie do Ciebie 👇"; },
            function($t) { return "⚡ {$t}\n\nKrótko i na temat:\n• co to zmienia\n• jak to wdrożyć dziś\n• co sprawdzić jutro"; },
            function($t) { return "📌 {$t}\n\n3 błędy, które widzę najczęściej:\n— …\n— …\n— …\n\nJeśli chcesz, podeślij URL – podpowiem co poprawić."; },
            function($t) { return "✅ {$t}\n\nSzybki plan działania (5 minut):\n1) …\n2) …\n3) …\n\nPotem dopiero dopieszczanie."; },
        ],
        'short' => [
            function($t) { return "⚡ {$t}\n\nJedno zdanie: …\n\nDwa kroki: 1) … 2) …"; },
            function($t) { return "📌 {$t}\n\nZrób dziś: …\nSprawdź jutro: …"; },
        ],
        'bullets' => [
            function($t) { return "📋 {$t}\n\nChecklist:\n• …\n• …\n• …"; },
            function($t) { return "✅ {$t}\n\n3 szybkie wskazówki:\n1) …\n2) …\n3) …"; },
        ],
        'story' => [
            function($t) { return "📍 {$t}\n\nKrótka historia: …\n\nWniosek: …\nNastępny krok: …"; },
            function($t) { return "🧩 {$t}\n\nProblem: …\nCo zrobiliśmy: …\nEfekt: …"; },
        ],
    ];

    if (!isset($templates[$style])) { $style = 'mixed'; }
    $pool = $templates[$style];
    $tpl = $pool[$seed % count($pool)];

    $title = mb_substr($topic, 0, 80);
    $body = $tpl($topic);

    if ($tone !== '') {
        $body = "[Ton: {$tone}]\n" . $body;
    }

    if ($g !== '') {
        $body .= "\n\n—\nWytyczne: " . $g;
    }

    // CTA (deterministic pick)
    if (!empty($ctas)) {
        $cta = $ctas[$seed % count($ctas)];
        if ($cta !== '') {
            $body .= "\n\n" . $cta;
        }
    }

    // Hashtags
    $tagline = "#Heterodyna #SEO";
    if (!empty($hashes)) {
        $extra = [];
        foreach ($hashes as $h) {
            if ($h === '') continue;
            if ($h[0] !== '#') { $h = '#' . preg_replace('/\s+/', '', $h); }
            $extra[] = $h;
        }
        if (!empty($extra)) {
            $tagline .= " " . implode(' ', array_slice($extra, 0, 12));
        }
    }
    $body .= "\n\n" . $tagline;

    // Basic banned-words cleanup
    if (!empty($banned)) {
        foreach ($banned as $bw) {
            if ($bw === '') continue;
            $body = str_ireplace($bw, '', $body);
        }
        $body = preg_replace('/\n{3,}/', "\n\n", $body);
    }

    // Emoji mode
    if ($emoji_mode === 'off') {
        $body = preg_replace('/[\x{1F300}-\x{1FAFF}]/u', '', $body);
    }

    return ['title' => $title, 'content' => trim($body)];
}

    /**
     * Compute next publish slot based on schedule config (blog timezone).
     * Returns mysql datetime string or empty string for immediate (NULL schedule_at).
     */
    public static function compute_next_slot($schedule): string {
        $schedule = is_array($schedule) ? $schedule : [];
        $mode = isset($schedule['mode']) ? (string)$schedule['mode'] : 'immediate';
        if ($mode !== 'next_slot') { return ''; }

        $days = (isset($schedule['days']) && is_array($schedule['days'])) ? $schedule['days'] : ['mon','tue','wed','thu','fri'];
        $hour_from = isset($schedule['hour_from']) ? (int)$schedule['hour_from'] : 9;
        $hour_to = isset($schedule['hour_to']) ? (int)$schedule['hour_to'] : 18;
        $interval = isset($schedule['interval']) ? (int)$schedule['interval'] : 30;
        $interval = max(5, min(180, $interval));

        $allowed = array_flip(array_values($days));
        $map = ['mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6,'sun'=>7];

        try {
            $tz = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone('UTC');
        } catch (Exception $e) {
            $tz = new DateTimeZone('UTC');
        }

        $now_ts = (int) current_time('timestamp');
        $dt = (new DateTimeImmutable('@'.$now_ts))->setTimezone($tz);

        // start slightly in the future and round up to interval
        $dt = $dt->modify('+2 minutes');
        $min = (int)$dt->format('i');
        $add = ($interval - ($min % $interval)) % $interval;
        if ($add > 0) { $dt = $dt->modify('+' . $add . ' minutes'); }

        // scan forward up to 14 days
        for ($step = 0; $step < (14 * 24 * 60 / $interval); $step++) {
            $dow_key = strtolower($dt->format('D')); // mon,tue...
            $dow_key = substr($dow_key, 0, 3);
            if (isset($allowed[$dow_key])) {
                $h = (int)$dt->format('G');
                if ($h >= $hour_from && $h <= $hour_to) {
                    return $dt->format('Y-m-d H:i:s');
                }
            }
            $dt = $dt->modify('+' . $interval . ' minutes');
        }
        return '';
    }

    /**
     * Create campaign.
     * Backward compatible:
     *  - old: $account_ids = [1,2,3]
     *  - new: $account_ids = ['accounts'=>[1,2], 'providers'=>['x','linkedin']]
     */
    public static function create_campaign($name, $post_id, $account_ids, $mode='dry', $schedule_at=''): int {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_campaigns';
        $now = current_time('mysql');
        $targets = $account_ids;
        // normalize
        if (is_array($targets) && (isset($targets['accounts']) || isset($targets['providers']))) {
            $acc = isset($targets['accounts']) ? (array)$targets['accounts'] : [];
            $prov = isset($targets['providers']) ? (array)$targets['providers'] : [];
            $targets = [
                'accounts' => array_values(array_filter(array_map('intval', $acc))),
                'providers' => array_values(array_filter(array_map('sanitize_key', $prov))),
            ];
        } else {
            $targets = [
                'accounts' => array_values(array_filter(array_map('intval', (array)$targets))),
                'providers' => [],
            ];
        }
        $wpdb->insert($t, [
            'name' => sanitize_text_field($name),
            'post_id' => (int)$post_id ?: null,
            'targets_json' => wp_json_encode($targets, JSON_UNESCAPED_UNICODE),
            'schedule_at' => $schedule_at ? sanitize_text_field($schedule_at) : null,
            'mode' => in_array($mode, ['dry','live'], true) ? $mode : 'dry',
            'status' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $cid = (int)$wpdb->insert_id;
        self::enqueue_campaign($cid);
        self::log('info', 'Campaign created', ['campaign_id'=>$cid,'mode'=>$mode,'targets'=>$targets]);
        return $cid;
    }

    public static function enqueue_campaign($campaign_id) {
        global $wpdb;
        $tC = $wpdb->prefix . 'het_seo_social_campaigns';
        $tQ = $wpdb->prefix . 'het_seo_social_queue';
        $tA = $wpdb->prefix . 'het_seo_social_accounts';
        $tP = $wpdb->prefix . 'het_seo_social_posts';

        $c = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tC WHERE id=%d", (int)$campaign_id), ARRAY_A);
        if (!$c) return;

        $targets_raw = json_decode((string)$c['targets_json'], true);
        if (!is_array($targets_raw)) { $targets_raw = []; }

        // normalize legacy and new shapes
        if (isset($targets_raw['accounts']) || isset($targets_raw['providers'])) {
            $targets = isset($targets_raw['accounts']) ? (array)$targets_raw['accounts'] : [];
            $providers = isset($targets_raw['providers']) ? (array)$targets_raw['providers'] : [];
        } else {
            $targets = (array)$targets_raw;
            $providers = [];
        }
        $targets = array_values(array_filter(array_map('intval', (array)$targets)));
        $providers = array_values(array_filter(array_map('sanitize_key', (array)$providers)));

        // If campaign has no explicit accounts but has providers, map providers -> enabled accounts.
        if (empty($targets) && !empty($providers)) {
            $in = implode("','", array_map('esc_sql', $providers));
            $targets = array_map('intval', $wpdb->get_col("SELECT id FROM $tA WHERE is_enabled=1 AND provider IN ('$in')"));
        }

        // If campaign has no explicit targets, use enabled accounts; optionally restrict by default channel providers.
        if (empty($targets)) {
            $sm = get_option('het_seo_socialmedia', []);
            $channels = (is_array($sm) && isset($sm['channels']) && is_array($sm['channels'])) ? $sm['channels'] : [];
            $prov = (is_array($channels) && isset($channels['providers']) && is_array($channels['providers'])) ? $channels['providers'] : [];
            $prov = array_values(array_filter(array_map('sanitize_key', (array)$prov)));

            if (!empty($prov)) {
                $in = implode("','", array_map('esc_sql', $prov));
                $targets = array_map('intval', $wpdb->get_col("SELECT id FROM $tA WHERE is_enabled=1 AND provider IN ('$in')"));
            } else {
                $targets = array_map('intval', $wpdb->get_col("SELECT id FROM $tA WHERE is_enabled=1"));
            }
        }

        $post_id = (int)($c['post_id'] ?? 0);
        $src_url = '';
        if ($post_id > 0) {
            $src_url = (string)$wpdb->get_var($wpdb->prepare("SELECT source_url FROM $tP WHERE id=%d", $post_id));
        }

        $now = current_time('mysql');
        $sm = get_option('het_seo_socialmedia', []);
        $schedule = (is_array($sm) && isset($sm['schedule']) && is_array($sm['schedule'])) ? $sm['schedule'] : [];
        foreach ($targets as $aid) {
            $acc = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tA WHERE id=%d", (int)$aid), ARRAY_A);
            if (!$acc) { continue; }

            $utm_url = $src_url ? self::build_utm_url($src_url, (string)$c['name'], $acc) : '';
            $payload = [
                'utm_url' => $utm_url,
                'source_url' => $src_url,
                'campaign' => (string)$c['name'],
                'provider' => (string)($acc['provider'] ?? ''),
                'account_label' => (string)($acc['account_label'] ?? ''),
                'mode' => (string)($c['mode'] ?? 'dry'),
            ];

            $max_attempts = 5;
            $schedule_cfg = (is_array($schedule) && !empty($schedule)) ? $schedule : [];
            if (isset($schedule_cfg['max_attempts'])) { $max_attempts = (int)$schedule_cfg['max_attempts']; }
            $max_attempts = max(1, min(10, $max_attempts));

            $next_run = !empty($c['schedule_at']) ? (string)$c['schedule_at'] : $now;

            // Idempotency / dedupe key: same campaign+account+post -> only one "sent" allowed.
            $dedupe_key = hash('sha256', (string)$campaign_id . '|' . (string)$aid . '|' . (string)$post_id);

            $wpdb->insert($tQ, [
                'campaign_id' => (int)$campaign_id,
                'account_id' => (int)$aid,
                'post_id' => $post_id ?: null,

                'status' => 'queued',
                'attempt' => 0,
                'max_attempts' => $max_attempts,

                'next_run_at' => $next_run,
                'dedupe_key' => $dedupe_key,

                'payload_json' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

public static function queue_tick() {
    // 03.06.027: reliable scheduler + retry/backoff + idempotency + locking (no dupes).
    global $wpdb;
    $tQ = $wpdb->prefix . 'het_seo_social_queue';
    $tC = $wpdb->prefix . 'het_seo_social_campaigns';
    $tA = $wpdb->prefix . 'het_seo_social_accounts';
    $tP = $wpdb->prefix . 'het_seo_social_posts';

    $sm = get_option('het_seo_socialmedia', []);
    $pub = self::get_publish_settings();
    $publish_mode = (string)($pub['mode'] ?? 'live');

    $schedule = (is_array($sm) && isset($sm['schedule']) && is_array($sm['schedule'])) ? $sm['schedule'] : [];
    $limit = isset($schedule['max_per_tick']) ? (int)$schedule['max_per_tick'] : 20;
    $limit = max(1, min(200, $limit));

    $lock_ttl_min = isset($schedule['lock_ttl_min']) ? (int)$schedule['lock_ttl_min'] : 15;
    $lock_ttl_min = max(5, min(60, $lock_ttl_min));

    $now_mysql = current_time('mysql');
    $now_ts = (int)current_time('timestamp');
    $lock_exp_mysql = gmdate('Y-m-d H:i:s', $now_ts - ($lock_ttl_min * 60));

    // Release stale "sending" locks (best-effort self-heal).
    $wpdb->query($wpdb->prepare("UPDATE {$tQ} SET status='queued', locked_at=NULL, locked_by=NULL WHERE status='sending' AND locked_at IS NOT NULL AND locked_at < %s", $lock_exp_mysql));

    // Pick runnable items: queued or retrying, next_run_at <= now, and campaign schedule_at respected.
    $sql = $wpdb->prepare(
        "SELECT q.*, c.mode, c.schedule_at
         FROM {$tQ} q
         LEFT JOIN {$tC} c ON c.id=q.campaign_id
         WHERE q.status IN ('queued','retrying')
           AND (q.next_run_at IS NULL OR q.next_run_at <= %s)
           AND (c.schedule_at IS NULL OR c.schedule_at <= %s)
         ORDER BY COALESCE(q.next_run_at, q.created_at) ASC, q.id ASC
         LIMIT %d",
        $now_mysql,
        $now_mysql,
        $limit
    );
    $items = $wpdb->get_results($sql, ARRAY_A);
    if (!is_array($items) || empty($items)) { return; }

    $worker = function_exists('wp_get_environment_type') ? ('wp:' . wp_get_environment_type()) : 'wp';
    $host = function_exists('php_uname') ? substr((string)php_uname('n'), 0, 32) : 'host';
    $locked_by = substr($worker . '@' . $host, 0, 64);

    $processed = 0;

    foreach ($items as $it) {
        $qid = (int)($it['id'] ?? 0);
        if ($qid <= 0) { continue; }

        // Acquire lock atomically
        $old_status = (string)($it['status'] ?? 'queued');
        $affected = $wpdb->query($wpdb->prepare(
            "UPDATE {$tQ}
             SET status='sending', locked_at=%s, locked_by=%s, updated_at=%s
             WHERE id=%d
               AND status IN ('queued','retrying')
               AND (locked_at IS NULL OR locked_at < %s)",
            $now_mysql, $locked_by, $now_mysql, $qid, $lock_exp_mysql
        ));
        if (!$affected) { continue; }

        // Re-read the row (we need fresh attempt/max)
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tQ} WHERE id=%d", $qid), ARRAY_A);
        if (!$row) { continue; }

        $attempt = (int)($row['attempt'] ?? 0);
        $max_attempts = (int)($row['max_attempts'] ?? 5);
        $attempt_next = $attempt + 1;

        // bump attempt now (so crashes are visible)
        $wpdb->update($tQ, ['attempt' => $attempt_next, 'updated_at' => $now_mysql], ['id' => $qid]);

        $mode = (string)($it['mode'] ?? 'dry');

        // Idempotency: if already sent with same dedupe_key, skip.
        $dedupe_key = (string)($row['dedupe_key'] ?? '');
        if ($dedupe_key !== '') {
            $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$tQ} WHERE dedupe_key=%s AND status='sent' AND id<>%d", $dedupe_key, $qid));
            if ($exists > 0) {
                $wpdb->update($tQ, [
                    'status' => 'skipped_duplicate',
                    'locked_at' => null,
                    'locked_by' => null,
                    'updated_at' => $now_mysql,
                ], ['id' => $qid]);
                self::log('info', 'Queue skipped (duplicate dedupe_key)', ['queue_id'=>$qid,'dedupe_key'=>$dedupe_key]);
                $processed++;
                continue;
            }
        }

        // Dry-run: always mark as sent.
        if ($mode !== 'live') {
            $wpdb->update($tQ, [
                'status' => 'sent',
                'locked_at' => null,
                'locked_by' => null,
                'last_error' => null,
                'updated_at' => $now_mysql,
            ], ['id' => $qid]);
            $processed++;
            continue;
        }

        // SAFE publish: hold live items for manual approval.
        if ($mode === 'live' && $publish_mode === 'safe') {
            $wpdb->update($tQ, [
                'status' => 'waiting_approval',
                'locked_at' => null,
                'locked_by' => null,
                'updated_at' => $now_mysql,
            ], ['id' => $qid]);
            $processed++;
            continue;
        }

        // Load account + payload
        $acc_id = (int)($row['account_id'] ?? 0);
        $acc = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tA} WHERE id=%d", $acc_id), ARRAY_A);
        if (!$acc) {
            $wpdb->update($tQ, [
                'status' => 'failed',
                'locked_at' => null,
                'locked_by' => null,
                'last_error' => 'Account missing',
                'updated_at' => $now_mysql,
            ], ['id' => $qid]);
            self::log('error', 'Queue failed: account missing', ['queue_id'=>$qid,'account_id'=>$acc_id]);
            $processed++;
            continue;
        }

        $payload = [];
        if (!empty($row['payload_json'])) {
            $tmp = json_decode((string)$row['payload_json'], true);
            if (is_array($tmp)) { $payload = $tmp; }
        }
        $provider = isset($payload['provider']) ? sanitize_key($payload['provider']) : sanitize_key((string)($acc['provider'] ?? ''));

        // Limits (per provider / per campaign, today)
        $lim = self::check_limits_or_error($provider, (int)($row['campaign_id'] ?? 0));
        if (empty($lim['ok'])) {
            $wpdb->update($tQ, [
                'status' => 'blocked',
                'locked_at' => null,
                'locked_by' => null,
                'last_error' => (string)($lim['err'] ?? 'Limit'),
                'updated_at' => $now_mysql,
            ], ['id' => $qid]);
            self::log('warn', 'Queue blocked by limits', ['queue_id'=>$qid,'provider'=>$provider,'err'=>$lim['err'] ?? '']);
            $processed++;
            continue;
        }

        // Live mode: supported providers.
        if (!in_array($provider, ['x','linkedin'], true)) {
            $wpdb->update($tQ, [
                'status' => 'blocked',
                'locked_at' => null,
                'locked_by' => null,
                'last_error' => 'Provider not implemented in live mode: ' . $provider,
                'updated_at' => $now_mysql,
            ], ['id' => $qid]);
            $processed++;
            continue;
        }

        // Build text from post content + optional link.
        $post_id = (int)($row['post_id'] ?? 0);
        $content = '';
        $media = [];
        if ($post_id > 0) {
            $content = (string)$wpdb->get_var($wpdb->prepare("SELECT content FROM {$tP} WHERE id=%d", $post_id));
            $mj = (string)$wpdb->get_var($wpdb->prepare("SELECT media_json FROM {$tP} WHERE id=%d", $post_id));
            if ($mj !== '') {
                $tmpm = json_decode($mj, true);
                if (is_array($tmpm)) { $media = $tmpm; }
            }
        }
        $text = trim($content);
        $utm = isset($payload['utm_url']) ? trim((string)$payload['utm_url']) : '';
        if ($utm !== '') {
            if ($text !== '') { $text .= "\n\n"; }
            $text .= $utm;
        }

        // Apply per-provider templates before refresh/publish.
        $fmt = self::format_text_for_provider($provider, $text, self::get_socialmedia_templates());
        if (!empty($fmt['text'])) { $text = (string)$fmt['text']; }

        self::maybe_refresh_token($acc);

        // --- publish (reuses 03.06.026 media flow) ---
        $res = null;
        $media_url = '';
        $media_ids = [];
        $media_first = null;

        if (is_array($media) && !empty($media)) {
            foreach ($media as $m) {
                if (is_array($m) && (!empty($m['attachment_id']) || !empty($m['url']))) { $media_first = $m; break; }
            }
        }

        if (is_array($media_first)) {
            if (!empty($media_first['url'])) { $media_url = trim((string)$media_first['url']); }
            if (empty($media_url) && !empty($media_first['attachment_id'])) {
                $media_url = (string)wp_get_attachment_url((int)$media_first['attachment_id']);
            }

            $resolved = self::resolve_media_item($media_first);

            if ($provider === 'linkedin') {
                $token = self::get_account_setting($acc, 'li_access_token', '');
                $author = self::get_account_setting($acc, 'li_author_urn', '');

                if (!empty($resolved['ok']) && self::is_image_mime((string)$resolved['mime'])) {
                    $up = self::li_upload_image_asset($token, $author, (string)$resolved['path'], (string)$resolved['mime']);
                    if (!empty($up['ok']) && !empty($up['asset'])) {
                        $res = self::li_publish_post_with_image_asset($token, $author, $text, (string)$up['asset'], 'PUBLIC');
                        self::log('info', 'LinkedIn media uploaded as asset (IMAGE)', ['queue_id'=>$qid,'asset'=>$up['asset']]);
                    } else {
                        self::log('warn', 'LinkedIn asset upload failed, falling back to ARTICLE', ['queue_id'=>$qid,'err'=>$up]);
                        $res = ($media_url !== '') ? self::li_publish_post_with_url($token, $author, $text, $media_url, 'PUBLIC') : self::li_publish_post($token, $author, $text, 'PUBLIC');
                    }
                } else {
                    $res = ($media_url !== '') ? self::li_publish_post_with_url($token, $author, $text, $media_url, 'PUBLIC') : self::li_publish_post($token, $author, $text, 'PUBLIC');
                }

                if (!empty($resolved['ok']) && ($resolved['kind'] ?? '') === 'url' && !empty($resolved['path']) && file_exists($resolved['path'])) { @unlink($resolved['path']); }
            }

            if ($provider === 'x') {
                $token = self::get_account_setting($acc, 'x_access_token', '');

                if (!empty($resolved['ok']) && (self::is_image_mime((string)$resolved['mime']) || self::is_video_mime((string)$resolved['mime']))) {
                    $cat = self::is_video_mime((string)$resolved['mime']) ? 'tweet_video' : 'tweet_image';
                    $upx = self::x_upload_media_chunked($token, (string)$resolved['path'], (string)$resolved['mime'], $cat);
                    if (!empty($upx['ok']) && !empty($upx['media_id'])) {
                        $media_ids[] = (string)$upx['media_id'];
                        self::log('info', 'X media uploaded (chunked)', ['queue_id'=>$qid,'media_id'=>$upx['media_id'],'mime'=>$resolved['mime']]);
                    } else {
                        self::log('warn', 'X media upload failed, using URL fallback', ['queue_id'=>$qid,'err'=>$upx]);
                        if ($media_url !== '' && strpos($text, $media_url) === false) {
                            $text .= "\n\n" . $media_url;
                            $fmt2 = self::format_text_for_provider($provider, $text, self::get_socialmedia_templates());
                            $text = (string)($fmt2['text'] ?? $text);
                        }
                        if ($media_url !== '') { self::log('info', 'Media appended as URL for X (fallback)', ['queue_id'=>$qid,'url'=>$media_url]); }
                    }
                } else {
                    if ($media_url !== '' && strpos($text, $media_url) === false) {
                        $text .= "\n\n" . $media_url;
                        $fmt2 = self::format_text_for_provider($provider, $text, self::get_socialmedia_templates());
                        $text = (string)($fmt2['text'] ?? $text);
                    }
                    if ($media_url !== '') { self::log('info', 'Media appended as URL for X (no local file)', ['queue_id'=>$qid,'url'=>$media_url]); }
                }

                if (!empty($resolved['ok']) && ($resolved['kind'] ?? '') === 'url' && !empty($resolved['path']) && file_exists($resolved['path'])) { @unlink($resolved['path']); }
            }
        }

        // Publish text (or attach URL fallback for LI if not already set)
        if ($provider === 'x') {
            $token = self::get_account_setting($acc, 'x_access_token', '');
            $res = self::x_publish_tweet($token, $text, $media_ids);
        } else {
            if (!isset($res) || !is_array($res)) {
                $token = self::get_account_setting($acc, 'li_access_token', '');
                $author = self::get_account_setting($acc, 'li_author_urn', '');
                $res = ($media_url !== '') ? self::li_publish_post_with_url($token, $author, $text, $media_url, 'PUBLIC') : self::li_publish_post($token, $author, $text, 'PUBLIC');
            }
        }

        $external_ref = '';
        if (is_array($res) && !empty($res['ok'])) {
            if ($provider === 'x') {
                $external_ref = (string)($res['json']['data']['id'] ?? '');
            } else {
                $external_ref = (string)($res['location'] ?? '');
            }
        }

        if (!is_array($res) || empty($res['ok'])) {
            $err = is_array($res) ? (string)($res['err'] ?? ($res['raw'] ?? 'Unknown error')) : 'Unknown error';

            // Exponential backoff (minutes): 5,10,20,40,60...
            $backoff_min = min(60, 5 * (int)pow(2, max(0, $attempt_next - 1)));
            $next_run = gmdate('Y-m-d H:i:s', $now_ts + ($backoff_min * 60));

            $final_status = ($attempt_next >= $max_attempts) ? 'failed' : 'retrying';

            $wpdb->update($tQ, [
                'status' => $final_status,
                'next_run_at' => ($final_status === 'retrying') ? $next_run : null,
                'locked_at' => null,
                'locked_by' => null,
                'last_error' => mb_substr($err, 0, 500),
                'last_response_json' => wp_json_encode($res, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now_mysql,
            ], ['id' => $qid]);

            self::log('error', 'Queue publish failed', [
                'queue_id'=>$qid,
                'provider'=>$provider,
                'attempt'=>$attempt_next,
                'max_attempts'=>$max_attempts,
                'next_run_at'=>($final_status==='retrying') ? $next_run : null,
                'err'=>$err
            ]);
            $processed++;
            continue;
        }

        $wpdb->update($tQ, [
            'status' => 'sent',
            'next_run_at' => null,
            'locked_at' => null,
            'locked_by' => null,
            'last_error' => null,
            'external_ref' => $external_ref ?: null,
            'last_response_json' => wp_json_encode($res, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now_mysql,
        ], ['id' => $qid]);

        self::log('info', 'Queue published', ['queue_id'=>$qid,'provider'=>$provider,'external_ref'=>$external_ref,'resp'=>$res]);
        $processed++;
    }

    self::log('info', 'Queue tick processed', ['count' => $processed, 'limit'=>$limit, 'locked_by'=>$locked_by]);
}


    public static function list_queue($limit=200): array {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_queue';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t ORDER BY id DESC LIMIT %d", (int)$limit), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public static function list_logs($limit=200): array {
        global $wpdb;
        $t = $wpdb->prefix . 'het_seo_social_logs';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM $t ORDER BY id DESC LIMIT %d", (int)$limit), ARRAY_A);
        return is_array($rows) ? $rows : [];
    }


    // ===== Queue actions (03.06.027) =====

    public static function approve_and_send_queue_item(int $queue_id): array {
        global $wpdb;
        $tQ = $wpdb->prefix . 'het_seo_social_queue';
        $queue_id = (int)$queue_id;
        if ($queue_id <= 0) { return ['ok'=>false,'err'=>'Invalid queue_id']; }

        $now = current_time('mysql');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tQ} WHERE id=%d", $queue_id), ARRAY_A);
        if (!$row) { return ['ok'=>false,'err'=>'Queue item not found']; }
        if ((string)$row['status'] !== 'waiting_approval') { return ['ok'=>false,'err'=>'Not in waiting_approval']; }

        $wpdb->update($tQ, [
            'status' => 'queued',
            'next_run_at' => $now,
            'locked_at' => null,
            'locked_by' => null,
            'updated_at' => $now,
        ], ['id'=>$queue_id]);

        // best-effort immediate send
        self::queue_tick();
        return ['ok'=>true];
    }

    public static function reject_queue_item(int $queue_id, string $reason = 'Rejected'): bool {
        global $wpdb;
        $tQ = $wpdb->prefix . 'het_seo_social_queue';
        $now = current_time('mysql');
        $queue_id = (int)$queue_id;
        if ($queue_id <= 0) { return false; }
        $wpdb->update($tQ, [
            'status' => 'rejected',
            'last_error' => mb_substr($reason, 0, 500),
            'next_run_at' => null,
            'locked_at' => null,
            'locked_by' => null,
            'updated_at' => $now,
        ], ['id'=>$queue_id]);
        return true;
    }

    public static function retry_queue_item(int $queue_id): bool {
        global $wpdb;
        $tQ = $wpdb->prefix . 'het_seo_social_queue';
        $now = current_time('mysql');
        $queue_id = (int)$queue_id;
        if ($queue_id <= 0) { return false; }
        $wpdb->update($tQ, [
            'status' => 'retrying',
            'next_run_at' => $now,
            'locked_at' => null,
            'locked_by' => null,
            'updated_at' => $now,
        ], ['id'=>$queue_id]);
        return true;
    }

    public static function cancel_queue_item(int $queue_id, string $reason = 'Cancelled'): bool {
        global $wpdb;
        $tQ = $wpdb->prefix . 'het_seo_social_queue';
        $now = current_time('mysql');
        $queue_id = (int)$queue_id;
        if ($queue_id <= 0) { return false; }
        $wpdb->update($tQ, [
            'status' => 'cancelled',
            'last_error' => mb_substr($reason, 0, 500),
            'next_run_at' => null,
            'locked_at' => null,
            'locked_by' => null,
            'updated_at' => $now,
        ], ['id'=>$queue_id]);
        return true;
    }


// ===== OAuth / PKCE (03.06.023) =====
public static function get_oauth_settings(): array {
    $o = get_option('het_seo_social_oauth', []);
    return is_array($o) ? $o : [];
}

public static function save_oauth_settings(array $o): void {
    update_option('het_seo_social_oauth', $o, false);
}

private static function b64url(string $bin): string {
    return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
}

private static function pkce_verifier(int $len = 64): string {
    $raw = wp_generate_password($len, false, false);
    return preg_replace('/[^a-zA-Z0-9\-\._~]/', '', $raw);
}

private static function pkce_challenge(string $verifier): string {
    return self::b64url(hash('sha256', $verifier, true));
}

private static function oauth_redirect_uri(): string {
    return admin_url('admin.php?page=het-seo-social&tab=accounts');
}

private static function oauth_transient_key(string $state): string {
    $uid = get_current_user_id();
    return 'het_seo_oauth_' . $uid . '_' . sanitize_key($state);
}

public static function maybe_refresh_token(array $acc): void {
    // Refresh tokens best-effort, only if we have refresh token and expiry is near.
    $provider = (string)($acc['provider'] ?? '');
    $settings = json_decode((string)($acc['settings_json'] ?? ''), true);
    if (!is_array($settings)) { $settings = []; }

    if ($provider === 'x') {
        $refresh = (string)($settings['x_refresh_token'] ?? '');
        $expires_at = (string)($settings['x_expires_at'] ?? '');
        if ($refresh === '' || $expires_at === '') { return; }
        $ts = strtotime($expires_at);
        if (!$ts || $ts > time() + 300) { return; } // refresh if expires in <=5min
        $oauth = self::get_oauth_settings();
        $client_id = (string)($oauth['x']['client_id'] ?? '');
        if ($client_id === '') { return; }

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id' => $client_id,
        ];
        $resp = wp_remote_post('https://api.twitter.com/2/oauth2/token', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query($body),
        ]);
        if (is_wp_error($resp)) { self::log('error','X refresh failed',['err'=>$resp->get_error_message()]); return; }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);
        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data) || empty($data['access_token'])) {
            self::log('error','X refresh failed',['code'=>$code,'raw'=>$raw]);
            return;
        }
        $settings['x_access_token'] = (string)$data['access_token'];
        if (!empty($data['refresh_token'])) { $settings['x_refresh_token'] = (string)$data['refresh_token']; }
        if (!empty($data['expires_in'])) {
            $settings['x_expires_at'] = gmdate('Y-m-d H:i:s', time() + (int)$data['expires_in']);
        }
        self::update_account_settings((int)$acc['id'], $settings);
        self::log('info','X token refreshed',['account_id'=>(int)$acc['id']]);
    }

    if ($provider === 'linkedin') {
        $refresh = (string)($settings['li_refresh_token'] ?? '');
        $expires_at = (string)($settings['li_expires_at'] ?? '');
        if ($refresh === '' || $expires_at === '') { return; }
        $ts = strtotime($expires_at);
        if (!$ts || $ts > time() + 300) { return; }
        $oauth = self::get_oauth_settings();
        $client_id = (string)($oauth['linkedin']['client_id'] ?? '');
        $client_secret = (string)($oauth['linkedin']['client_secret'] ?? '');
        if ($client_id === '' || $client_secret === '') { return; }

        $body = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh,
            'client_id' => $client_id,
            'client_secret' => $client_secret,
        ];
        $resp = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            'body' => http_build_query($body),
        ]);
        if (is_wp_error($resp)) { self::log('error','LinkedIn refresh failed',['err'=>$resp->get_error_message()]); return; }
        $code = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);
        $data = json_decode($raw, true);
        if ($code < 200 || $code >= 300 || !is_array($data) || empty($data['access_token'])) {
            self::log('error','LinkedIn refresh failed',['code'=>$code,'raw'=>$raw]);
            return;
        }
        $settings['li_access_token'] = (string)$data['access_token'];
        if (!empty($data['refresh_token'])) { $settings['li_refresh_token'] = (string)$data['refresh_token']; }
        if (!empty($data['expires_in'])) {
            $settings['li_expires_at'] = gmdate('Y-m-d H:i:s', time() + (int)$data['expires_in']);
        }
        self::update_account_settings((int)$acc['id'], $settings);
        self::log('info','LinkedIn token refreshed',['account_id'=>(int)$acc['id']]);
    }
}

public static function handle_oauth(): void {
    if (!is_admin() || !current_user_can('manage_options')) { return; }

    // Start flow
    if (isset($_GET['het_oauth']) && isset($_GET['_wpnonce']) && wp_verify_nonce((string)$_GET['_wpnonce'], 'het_seo_oauth_start')) {
        $provider = sanitize_key((string)$_GET['het_oauth']);
        $account_id = isset($_GET['account_id']) ? (int)$_GET['account_id'] : 0;
        if (!in_array($provider, ['x','linkedin'], true) || $account_id <= 0) { return; }

        $oauth = self::get_oauth_settings();
        $client_id = (string)($oauth[$provider]['client_id'] ?? '');
        $client_secret = (string)($oauth[$provider]['client_secret'] ?? '');
        if ($client_id === '' || ($provider === 'linkedin' && $client_secret === '')) {
            self::log('error','OAuth config missing',['provider'=>$provider]);
            return;
        }

        $state = wp_generate_password(24, false, false);
        $verifier = self::pkce_verifier(72);
        $challenge = self::pkce_challenge($verifier);
        $redirect = self::oauth_redirect_uri();

        set_transient(self::oauth_transient_key($state), [
            'provider' => $provider,
            'account_id' => $account_id,
            'verifier' => $verifier,
            'redirect' => $redirect,
            'created' => time(),
        ], 15 * MINUTE_IN_SECONDS);

        if ($provider === 'x') {
            $scope = rawurlencode('tweet.write users.read offline.access');
            $url = 'https://twitter.com/i/oauth2/authorize'
                . '?response_type=code'
                . '&client_id=' . rawurlencode($client_id)
                . '&redirect_uri=' . rawurlencode($redirect)
                . '&scope=' . $scope
                . '&state=' . rawurlencode($state)
                . '&code_challenge=' . rawurlencode($challenge)
                . '&code_challenge_method=S256';
        } else {
            $scope = rawurlencode('w_member_social r_liteprofile offline_access');
            $url = 'https://www.linkedin.com/oauth/v2/authorization'
                . '?response_type=code'
                . '&client_id=' . rawurlencode($client_id)
                . '&redirect_uri=' . rawurlencode($redirect)
                . '&scope=' . $scope
                . '&state=' . rawurlencode($state)
                . '&code_challenge=' . rawurlencode($challenge)
                . '&code_challenge_method=S256';
        }

        wp_redirect($url);
        exit;
    }

    // Callback
    if (isset($_GET['code'], $_GET['state'], $_GET['tab']) && (string)$_GET['tab'] === 'accounts') {
        $code = (string)$_GET['code'];
        $state = (string)$_GET['state'];
        $payload = get_transient(self::oauth_transient_key($state));
        if (!is_array($payload) || empty($payload['provider']) || empty($payload['verifier'])) { return; }

        delete_transient(self::oauth_transient_key($state));
        $provider = (string)$payload['provider'];
        $account_id = (int)$payload['account_id'];
        $verifier = (string)$payload['verifier'];
        $redirect = (string)$payload['redirect'];

        $oauth = self::get_oauth_settings();
        $client_id = (string)($oauth[$provider]['client_id'] ?? '');
        $client_secret = (string)($oauth[$provider]['client_secret'] ?? '');

        if ($provider === 'x') {
            $body = [
                'grant_type' => 'authorization_code',
                'client_id' => $client_id,
                'redirect_uri' => $redirect,
                'code' => $code,
                'code_verifier' => $verifier,
            ];
            $resp = wp_remote_post('https://api.twitter.com/2/oauth2/token', [
                'timeout' => 20,
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query($body),
            ]);
        } else {
            $body = [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirect,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'code_verifier' => $verifier,
            ];
            $resp = wp_remote_post('https://www.linkedin.com/oauth/v2/accessToken', [
                'timeout' => 20,
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                'body' => http_build_query($body),
            ]);
        }

        if (is_wp_error($resp)) {
            self::log('error','OAuth token exchange failed',['provider'=>$provider,'err'=>$resp->get_error_message()]);
            return;
        }
        $status = (int)wp_remote_retrieve_response_code($resp);
        $raw = (string)wp_remote_retrieve_body($resp);
        $data = json_decode($raw, true);
        if ($status < 200 || $status >= 300 || !is_array($data) || empty($data['access_token'])) {
            self::log('error','OAuth token exchange failed',['provider'=>$provider,'code'=>$status,'raw'=>$raw]);
            return;
        }

        $settings = [];
        $acc = self::get_account($account_id);
        if ($acc) {
            $settings = json_decode((string)($acc['settings_json'] ?? ''), true);
            if (!is_array($settings)) { $settings = []; }
        }

        if ($provider === 'x') {
            $settings['x_access_token'] = (string)$data['access_token'];
            if (!empty($data['refresh_token'])) { $settings['x_refresh_token'] = (string)$data['refresh_token']; }
            if (!empty($data['expires_in'])) { $settings['x_expires_at'] = gmdate('Y-m-d H:i:s', time() + (int)$data['expires_in']); }
        } else {
            $settings['li_access_token'] = (string)$data['access_token'];
            if (!empty($data['refresh_token'])) { $settings['li_refresh_token'] = (string)$data['refresh_token']; }
            if (!empty($data['expires_in'])) { $settings['li_expires_at'] = gmdate('Y-m-d H:i:s', time() + (int)$data['expires_in']); }
            // Auto-detect author URN if missing
            if (empty($settings['li_author_urn'])) {
                $me = wp_remote_get('https://api.linkedin.com/v2/me?projection=(id)', [
                    'timeout' => 20,
                    'headers' => ['Authorization' => 'Bearer ' . $settings['li_access_token']],
                ]);
                if (!is_wp_error($me)) {
                    $mr = (string)wp_remote_retrieve_body($me);
                    $md = json_decode($mr, true);
                    if (is_array($md) && !empty($md['id'])) {
                        $settings['li_author_urn'] = 'urn:li:person:' . (string)$md['id'];
                    }
                }
            }
        }

        self::update_account_settings($account_id, $settings);
        self::log('info','OAuth connected',['provider'=>$provider,'account_id'=>$account_id]);

        // Redirect back to accounts tab without code/state
        $back = remove_query_arg(['code','state'], self::oauth_redirect_uri());
        wp_redirect($back . '&het_notice=oauth_ok');
        exit;
    }
}

}

add_action('admin_init', ['HET_SEO_Social_Hub', 'handle_oauth']);
add_action('het_seo_social_queue_tick', ['HET_SEO_Social_Hub', 'queue_tick']);
add_action('het_seo_social_metrics_tick', ['HET_SEO_Social_Hub', 'metrics_tick']);
