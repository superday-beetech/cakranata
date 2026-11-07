<?php

declare(strict_types=1);

namespace Modules\Backend\Services;

use Config\Services;

class EmailSettingsService
{
    public function getConfig(): array
    {
        $settings = Services::app_settings()->getAll('email');

        return [
            'fromEmail' => $settings['mail_from'] ?? '',
            'fromName' => $settings['mail_name'] ?? '',
            'replyTo' => $settings['noreply_email'] ?? '',
            'protocol' => $settings['mail_protocol'] ?? 'smtp',
            'SMTPHost' => $settings['mail_host'] ?? '',
            'SMTPUser' => $settings['mail_user'] ?? '',
            'SMTPPass' => $settings['mail_pass'] ?? '',
            'SMTPPort' => (int) ($settings['mail_port'] ?? 465),
            'SMTPCrypto' => strtolower(
                (string) ($settings['mail_crypto'] ?? 'ssl')
            ),
            'mailType' => strtolower(
                (string) ($settings['mail_type'] ?? 'html')
            ),
            'charset' => $settings['mail_charset'] ?? 'UTF-8',
            'SMTPTimeout' => (int) ($settings['mail_timeout'] ?? 5),
            'SMTPKeepAlive' => ($settings['mail_keepalive'] ?? '0') === '1',
        ];
    }

    public function getPublicConfig(): array
    {
        $config = $this->getConfig();

        unset($config['SMTPPass']);

        return $config;
    }


    public function save(array $input): bool
    {
        $model = new \App\Models\SettingModel();

        $allowed = [
            'mail_from'     => 'fromEmail',
            'mail_name'     => 'fromName',
            'noreply_email' => 'replyTo',
            'mail_protocol' => 'protocol',
            'mail_host'     => 'SMTPHost',
            'mail_user'     => 'SMTPUser',
            'mail_port'     => 'SMTPPort',
            'mail_crypto'   => 'SMTPCrypto',
            'mail_type'     => 'mailType',
            'mail_charset'  => 'charset',
            'mail_timeout'  => 'SMTPTimeout',
            'mail_keepalive' => 'SMTPKeepAlive',
        ];

        $values = [];

        foreach ($allowed as $name => $field) {
            if (array_key_exists($field, $input)) {
                $values[$name] = trim((string) $input[$field]);
            }
        }

        if (isset($input['SMTPPass']) && $input['SMTPPass'] !== '') {
            $values['mail_pass'] = (string) $input['SMTPPass'];
        }

        $db = \Config\Database::connect();
        $db->transStart();

        foreach ($values as $name => $value) {
            $row = $model->where('name', $name)
                ->where('type', 'email')
                ->where('deleted', 0)
                ->first();

            if (! $row) {
                $db->transRollback();
                return false;
            }

            $model->update($row['id'], [
                'value' => $value,
            ]);
        }

        $db->transComplete();

        return $db->transStatus();
    }

    public function sendTest(string $recipient): bool
    {
        return $this->sendTemplate(
            $recipient,
            'test_email',
            [
                'recipient_name' => trim($recipient),
                'sent_at'        => date('d-m-Y H:i:s'),
            ]
        );
    }

    public function sendTest1111(string $recipient): bool
    {
        $config = $this->getConfig();

        $email = \Config\Services::email();
        $email->initialize($config);

        $email->clear(true);
        $email->setFrom(
            $config['fromEmail'],
            $config['fromName']
        );

        if (! empty($config['replyTo'])) {
            $email->setReplyTo(
                $config['replyTo'],
                $config['fromName']
            );
        }

        $email->setTo($recipient);
        $email->setSubject('Tes Email - ACN ERP');
        $email->setMessage(
            '<h3>Pengujian Email ACN ERP</h3>'
                . '<p>Jika Anda menerima pesan ini, pengiriman email berhasil.</p>'
        );

        return $email->send();
    }


    public function sendTemplate(
        string $recipient,
        string $templateCode,
        array $data = []
    ): bool {
        $recipient = trim($recipient);

        if (
            filter_var($recipient, FILTER_VALIDATE_EMAIL) === false
            || strlen($recipient) > 254
        ) {
            throw new \InvalidArgumentException(
                'Alamat email penerima tidak valid.'
            );
        }

        $template = (new EmailTemplateService())->render(
            $templateCode,
            $data
        );

        $config = $this->getConfig();

        $email = Services::email();
        $email->initialize($config);
        $email->clear(true);

        $fromEmail = trim(
            (string) ($template['from_email'] ?? '')
        );

        $fromName = trim(
            (string) ($template['from_name'] ?? '')
        );

        if ($fromEmail === '') {
            $fromEmail = trim((string) $config['fromEmail']);
        }

        if ($fromName === '') {
            $fromName = trim((string) $config['fromName']);
        }

        if (
            filter_var($fromEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new \RuntimeException(
                'Alamat email pengirim belum dikonfigurasi dengan benar.'
            );
        }

        $email->setFrom($fromEmail, $fromName);

        $replyTo = trim(
            (string) (
                $template['reply_to']
                ?? $config['replyTo']
                ?? ''
            )
        );

        if ($replyTo !== '') {
            if (filter_var($replyTo, FILTER_VALIDATE_EMAIL) === false) {
                throw new \RuntimeException(
                    'Alamat reply-to tidak valid.'
                );
            }

            $email->setReplyTo($replyTo, $fromName);
        }

        $email->setTo($recipient);
        $email->setSubject($template['subject']);

        if (($config['mailType'] ?? 'html') === 'text') {
            $email->setMessage($template['body_text']);
        } else {
            $email->setMessage($template['body_html']);
            $email->setAltMessage($template['body_text']);
        }

        return $email->send();
    }
}
