<?php

namespace TgLogger;

use Exception;

class TgLogger
{
    private string $url;
    private string $token;
    private int $chatId;
    private string $prefix;
    private int $level;
    private int $timeout;
    private string $msg = '';

    public function __construct(string $token, int $chatId, string $prefix = '')
    {
        $this->token = $token;
        $this->chatId = $chatId;
        $this->prefix = $prefix;
        $this->url = $config['base_uri'] ?? 'https://api.telegram.org';
        $this->level = Level::Trace;
        $this->timeout = 30;
    }

    public function setApiUrl(int $url): TgLogger
    {
        $this->url = $url ?? 'https://api.telegram.org';
        return $this;
    }

    public function setTimeout(int $timeout): TgLogger
    {
        $this->timeout = $timeout;
        return $this;
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
     */
    public function Panic(string $msg, ?array $fields): string
    {
        return $this->send(Level::Panic, $msg, $fields);
    }

    /**
     * @throws Exception
     */
    public function Fatal(string $msg, ?array $fields): string
    {
        return $this->send(Level::Fatal, $msg, $fields);
    }

    /**
     * @throws Exception
     */
    public function Error(string $msg, ?array $fields): string
    {
        return $this->send(Level::Error, $msg, $fields);
    }

    /**
     * @throws Exception
     */
    public function Warn(string $msg, ?array $fields): string
    {
        return $this->send(Level::Warn, $msg, $fields);
    }

    /**
     * @throws Exception
     */
    public function Info(string $msg, ?array $fields): string
    {
        return $this->send(Level::Info, $msg, $fields);
    }

    /**
     * @throws Exception
     */
    public function Log(string $msg, ?array $fields): string
    {
        return $this->send(Level::Info, $msg, $fields);
    }

    /**
     * @throws Exception
     */
    public function Debug(string $msg, ?array $fields): string
    {
        return $this->send(Level::Debug, $msg, $fields);
    }

    /**
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
                'content' => $data,
            ],
        ];

        $this->sendRaw("sendDocument", $payload);
    }

    /**
     * @throws Exception
     */
    private function sendRaw(string $method, array $payload = []): void
    {
        $resp = $this->requestPOST($method, $payload);
        if (!$resp) {
            throw new Exception('Response is nil.');
        }

        $ar = json_decode($resp, true);
        if ($ar['ok'] ?? false) {
            return;
        }

        throw new Exception(
            'telegram: ' .
            ($ar['description'] ?? 'unknown description') . ' (' . ($ar['error_code'] ?? '???') . ')');
    }

    /**
     * @throws Exception
     */
    private function requestPOST(string $method, array $fields): string
    {
        $delimiter = '-------------' . md5(mt_rand() . microtime());
        $postfields = $this->buildMultiPartRequest($delimiter, $fields);

        $headers = [
            'Content-Type:multipart/form-data; boundary=' . $delimiter,
            'Content-Length: ' . strlen($postfields),
        ];

        $options = [
            CURLOPT_URL => $this->url . '/bot' . $this->token . '/' . $method,
            CURLOPT_HEADER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);

        $info = curl_getinfo($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new Exception($err);
        }

        if ($info['http_code'] != 200) {
            throw new Exception('Status code ' . $info['http_code'] . ' ' . $response);
        }

        return $response;
    }

    function buildMultiPartRequest(string $delimiter, array $fields): string
    {
        $rn = "\r\n";

        $data = '';
        foreach ($fields as $name => $content) {
            $data .= '--' . $delimiter . $rn . 'Content-Disposition: form-data; name="' . $name . '"';

            if (is_array($content)) {
                $data .= '; filename="' . $content['filename'] . '"' . $rn;
                $data .= 'Content-Type: application/octet-stream';
                $content = $content['content'];
            }

            $data .= $rn . $rn . $content . $rn;
        }

        $data .= '--' . $delimiter . '--' . $rn;
        return $data;
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