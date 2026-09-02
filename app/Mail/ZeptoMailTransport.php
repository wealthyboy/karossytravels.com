<?php

namespace App\Mail;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ZeptoMailTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.zeptomail.com/v1.1/email',
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = [
            'from' => [
                'address' => $email->getFrom()[0]->getAddress(),
                'name'    => $email->getFrom()[0]->getName() ?: config('app.name'),
            ],
            'to'      => $this->formatAddresses($email->getTo()),
            'cc'      => $this->formatAddresses($email->getCc()),
            'bcc'     => $this->formatAddresses($email->getBcc()),
            'subject' => $email->getSubject(),
            'htmlbody' => $email->getHtmlBody(),
            'textbody' => $email->getTextBody(),
        ];

        // Attachments
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $attachments[] = [
                'content'   => base64_encode($attachment->getBody()),
                'name'      => $attachment->getFilename() ?: 'attachment',
                'mime_type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
            ];
        }
        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        $response = Http::withHeaders([
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => 'Zoho-enczapikey '.$this->apiKey,
        ])->timeout(20)->post($this->endpoint, $payload);

        if ($response->failed()) {
            Log::error('ZeptoMail send failed.', [
                'status'   => $response->status(),
                'response' => $response->body(),
            ]);

            throw new \RuntimeException('ZeptoMail API error ('.$response->status().'): '.$response->body());
        }
    }

    public function __toString(): string
    {
        return 'zeptomail';
    }

    /** @param \Symfony\Component\Mime\Address[] $addresses */
    private function formatAddresses(array $addresses): array
    {
        return array_values(array_map(fn ($addr) => [
            'email_address' => [
                'address' => $addr->getAddress(),
                'name'    => $addr->getName(),
            ],
        ], $addresses));
    }
}
