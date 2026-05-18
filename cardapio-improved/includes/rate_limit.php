<?php
function rate_limit(string $key, int $max = 10, int $window = 60): void {
    $ip   = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $file = sys_get_temp_dir() . '/rl_' . md5($key . $ip);
    $now  = time();
    $hits = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $hits = array_filter($hits, fn($t) => $t > $now - $window);
    if (count($hits) >= $max) {
        http_response_code(429);
        echo json_encode(['error' => 'Muitas requisições. Tente novamente em instantes.']);
        exit;
    }
    $hits[] = $now;
    file_put_contents($file, json_encode(array_values($hits)));
}