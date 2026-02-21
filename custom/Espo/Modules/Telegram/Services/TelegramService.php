<?php
namespace Espo\Modules\Telegram\Services;

use Espo\Core\Utils\Config;

class TelegramService
{

    public function __construct(
        private readonly Config $config,
    )
    {}

    public function sendMessage(string $message, ?string $chatId = null): bool
    {
        $targetChat = $chatId ?? $this->config->get('defaultChatId');
        $botToken = $this->config->get('botToken');

        if (!$botToken || !$targetChat) {
            return false;
        }
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $data = [
            'chat_id' => $targetChat,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $httpCode === 200;
    }
}