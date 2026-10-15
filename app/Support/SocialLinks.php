<?php

namespace App\Support;

final class SocialLinks
{
    public static function platforms(): array
    {
        return [
            'facebook' => ['label' => 'Facebook', 'icon' => 'bi-facebook', 'example' => 'https://www.facebook.com/your-page'],
            'instagram' => ['label' => 'Instagram', 'icon' => 'bi-instagram', 'example' => 'https://www.instagram.com/your-handle/'],
            'x' => ['label' => 'X (Twitter)', 'icon' => 'bi-twitter-x', 'example' => 'https://x.com/your-handle'],
            'linkedin' => ['label' => 'LinkedIn', 'icon' => 'bi-linkedin', 'example' => 'https://www.linkedin.com/company/your-page/'],
            'youtube' => ['label' => 'YouTube', 'icon' => 'bi-youtube', 'example' => 'https://www.youtube.com/@your-channel'],
            'tiktok' => ['label' => 'TikTok', 'icon' => 'bi-tiktok', 'example' => 'https://www.tiktok.com/@your-handle'],
            'threads' => ['label' => 'Threads', 'icon' => 'bi-threads', 'example' => 'Paste your full Threads profile URL'],
            'pinterest' => ['label' => 'Pinterest', 'icon' => 'bi-pinterest', 'example' => 'https://www.pinterest.com/your-handle/'],
            'snapchat' => ['label' => 'Snapchat', 'icon' => 'bi-snapchat', 'example' => 'https://www.snapchat.com/add/your-handle'],
            'telegram' => ['label' => 'Telegram', 'icon' => 'bi-telegram', 'example' => 'https://t.me/your-channel'],
            'whatsapp' => ['label' => 'WhatsApp', 'icon' => 'bi-whatsapp', 'example' => 'Paste your full WhatsApp contact or channel URL'],
        ];
    }

    public static function isSafeUrl(mixed $url): bool
    {
        return is_string($url)
            && strlen($url) <= 2048
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null;
    }

    public static function forFooter(array $links): array
    {
        $result = [];
        foreach (self::platforms() as $key => $platform) {
            $url = $links[$key] ?? null;
            if (self::isSafeUrl($url)) {
                $result[] = ['key' => $key, 'url' => $url, 'label' => $platform['label'], 'icon' => $platform['icon']];
            }
        }

        return $result;
    }
}
