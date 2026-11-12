<?php

declare(strict_types=1);

namespace App\Services;

use JsonException;
use RuntimeException;

final class ContactStorage
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__, 2) . '/var/contacts';
    }

    /** @throws RuntimeException */
    public function save(array $contact): string
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException('Unable to create the contacts directory.');
        }

        $timestamp = time();
        $safeEmail = preg_replace('/[^A-Za-z0-9@._+-]/', '_', $contact['email']);
        if (!is_string($safeEmail) || $safeEmail === '') {
            throw new RuntimeException('Unable to create a safe contact filename.');
        }

        do {
            $filename = sprintf('%d_%s.json', $timestamp, $safeEmail);
            $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
            $timestamp++;
        } while (file_exists($path));

        $createdAt = $timestamp - 1;
        $document = [
            'email' => $contact['email'],
            'subject' => $contact['subject'],
            'message' => $contact['message'],
            'dateOfCreation' => (string) $createdAt,
            'dateOfLastUpdate' => (string) $createdAt,
        ];

        try {
            $json = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the contact file.', previous: $exception);
        }

        $written = @file_put_contents($path, $json, LOCK_EX);
        if ($written !== strlen($json)) {
            if (file_exists($path)) {
                @unlink($path);
            }
            throw new RuntimeException('Unable to write the contact file.');
        }

        return $filename;
    }
}
