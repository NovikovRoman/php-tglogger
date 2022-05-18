<?php

namespace TgLogger;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class TgLogger
{
    private string $url;
    private string $token;
    private int $chatId;
    private string $prefix;
    private Client $httpClient;
    private int $level;
    private string $msg = '';

    public function __construct(string $token, int $chatId, string $prefix = '', array $config = ['timeout' => 30])
    {
        $this->token = $token;
        $this->chatId = $chatId;
        $this->prefix = $prefix;
        $this->url = $config['base_uri'] ?? 'https://api.telegram.org';
        $this->httpClient = new Client($config);
        $this->level = Level::Trace;
    }

    public function setPrefix(string $prefix): TgLogger
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setLevel(int $level): TgLogger
    {
        $this->level = $level;
        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    /**
     * @throws GuzzleException
     */
    public function Panic(string $msg, ?array $fields): string
    {
        return $this->send(Level::Panic, $msg, $fields);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function Fatal(string $msg, ?array $fields): string
    {
        return $this->send(Level::Fatal, $msg, $fields);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function Error(string $msg, ?array $fields): string
    {
        return $this->send(Level::Error, $msg, $fields);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function Warn(string $msg, ?array $fields): string
    {
        return $this->send(Level::Warn, $msg, $fields);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function Info(string $msg, ?array $fields): string
    {
        return $this->send(Level::Info, $msg, $fields);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function Log(string $msg, ?array $fields): string
    {
        return $this->send(Level::Info, $msg, $fields);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function Debug(string $msg, ?array $fields): string
    {
        return $this->send(Level::Debug, $msg, $fields);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    public function Trace(string $msg, ?array $fields): string
    {
        return $this->send(Level::Trace, $msg, $fields);
    }

    public function getMessage(): string
    {
        return $this->msg;
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function send(int $level, string $msg, ?array $fields): string
    {
        if ($level > $this->level) {
            return '';
        }

        $this->formatMessage($level, $msg, $fields);

        if (mb_strlen($this->msg, 'utf-8') > 1100) {
            $this->sendMessage(mb_substr($this->msg, 0, 1024) . '…');
            $this->sendDocument($this->msg);

        } else {
            $this->sendMessage($this->msg);
        }

        return $this->msg;
    }

    private function formatMessage(int $level, string $msg, ?array $fields)
    {
        $this->msg = $this->levelToString($level) . ' ' . $msg;

        if ($this->prefix != '') {
            $this->msg = '_' . $this->prefix . ':_ ' . $this->msg;
        }

        if ($fields && count($fields) > 0) {
            $s = '';
            foreach ($fields as $name => $value) {
                $s .= $name . ': ' . $value . "\n";
            }

            $this->msg .= sprintf("\n```\n%s```", $s);
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function sendMessage(string $msg)
    {
        $payload = [
            'chat_id' => $this->chatId,
            'text' => $msg,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true,
        ];

        $this->sendRaw("sendMessage", $payload);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function sendDocument(string $data)
    {
        $name = date('Y-m-d H:i:s') . '_full.log';
        $payload = [
            'chat_id' => $this->chatId,
            'caption' => 'full log',
            'file_name' => $name,
            'document' => [
                'filename' => $name,
                'contents' => $data,
            ],
        ];

        $this->sendRaw("sendDocument", $payload);
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function sendRaw(string $method, array $payload = []): void
    {
        $options = [
            'multipart' => [],
        ];

        foreach ($payload as $field => $value) {
            if (is_array($value)) {
                $ar = $value;

            } else {
                $ar = [
                    'contents' => $value,
                ];
            }

            $ar['name'] = $field;
            $options['multipart'][] = $ar;
        }

        $resp = $this->httpClient->request(
            'POST',
            $this->url . '/bot' . $this->token . '/' . $method,
            $options
        );

        if (!$resp) {
            throw new Exception('Response is nil.');
        }

        $ar = json_decode($resp->getBody()->getContents(), true);
        if ($ar['ok'] ?? false) {
            return;
        }

        throw new Exception(
            'telegram: ' .
            ($ar['description'] ?? 'unknown description') . ' (' . ($ar['error_code'] ?? '???') . ')');
    }

    private function levelToString(int $level): string
    {
        switch ($level) {
            case Level::Panic:
                return "🆘";
            case Level::Fatal:
                return "❌";
            case Level::Error:
                return "❗";
            case Level::Warn:
                return "⚠";
            case Level::Info:
                return "🗒";
            case Level::Debug:
                return "📝";
            case Level::Trace:
                return "📜";
        }

        return "⁉";
    }
}