<?php

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

    /** @return list<array{email: string, subject: string, message: string, dateOfCreation: string, dateOfLastUpdate: string}> */
    public function findAll(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $paths = glob($this->directory . DIRECTORY_SEPARATOR . '*.json');
        if ($paths === false) {
            throw new RuntimeException('Unable to list the contacts directory.');
        }
        sort($paths, SORT_STRING);

        $contacts = [];
        foreach ($paths as $path) {
            $contents = @file_get_contents($path);
            if (!is_string($contents)) {
                throw new RuntimeException('Unable to read a contact file.');
            }

            try {
                $decoded = json_decode($contents, false, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('A contact file contains invalid JSON.', previous: $exception);
            }

            if (!$decoded instanceof \stdClass) {
                throw new RuntimeException('A contact file must contain a JSON object.');
            }

            $contact = get_object_vars($decoded);
            $expectedFields = ['email', 'subject', 'message', 'dateOfCreation', 'dateOfLastUpdate'];
            if (array_diff(array_keys($contact), $expectedFields) !== [] || array_diff($expectedFields, array_keys($contact)) !== []) {
                throw new RuntimeException('A contact file has an invalid structure.');
            }

            foreach ($expectedFields as $field) {
                if (!is_string($contact[$field])) {
                    throw new RuntimeException('A contact file contains an invalid property type.');
                }
            }
            if (
                filter_var($contact['email'], FILTER_VALIDATE_EMAIL) === false
                || trim($contact['subject']) === ''
                || trim($contact['message']) === ''
                || preg_match('/^\d+$/', $contact['dateOfCreation']) !== 1
                || preg_match('/^\d+$/', $contact['dateOfLastUpdate']) !== 1
            ) {
                throw new RuntimeException('A contact file contains invalid values.');
            }

            $contacts[] = [
                'email' => $contact['email'],
                'subject' => $contact['subject'],
                'message' => $contact['message'],
                'dateOfCreation' => $contact['dateOfCreation'],
                'dateOfLastUpdate' => $contact['dateOfLastUpdate'],
            ];
        }

        return $contacts;
    }

    /** @return array{email: string, subject: string, message: string, dateOfCreation: string, dateOfLastUpdate: string}|null */
    public function findByFilename(string $filename): ?array
    {
        if (preg_match('/^\d+_[A-Za-z0-9@._+-]+\.json$/', $filename) !== 1) {
            return null;
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException('Unable to read a contact file.');
        }

        try {
            $contact = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('A contact file contains invalid JSON.', previous: $exception);
        }

        $expectedFields = ['email', 'subject', 'message', 'dateOfCreation', 'dateOfLastUpdate'];
        if (!is_array($contact) || array_diff(array_keys($contact), $expectedFields) !== [] || array_diff($expectedFields, array_keys($contact)) !== []) {
            throw new RuntimeException('A contact file has an invalid structure.');
        }
        foreach ($expectedFields as $field) {
            if (!is_string($contact[$field])) {
                throw new RuntimeException('A contact file contains an invalid property type.');
            }
        }
        if (
            filter_var($contact['email'], FILTER_VALIDATE_EMAIL) === false
            || trim($contact['subject']) === ''
            || trim($contact['message']) === ''
            || preg_match('/^\d+$/', $contact['dateOfCreation']) !== 1
            || preg_match('/^\d+$/', $contact['dateOfLastUpdate']) !== 1
        ) {
            throw new RuntimeException('A contact file contains invalid values.');
        }

        return $contact;
    }

    /** @param array<string, string> $changes
     *  @return array{email: string, subject: string, message: string, dateOfCreation: string, dateOfLastUpdate: string}|null
     */
    public function updateByFilename(string $filename, array $changes): ?array
    {
        $contact = $this->findByFilename($filename);
        if ($contact === null) {
            return null;
        }

        foreach ($changes as $field => $value) {
            $contact[$field] = $value;
        }
        $contact['dateOfLastUpdate'] = (string) max(time(), (int) $contact['dateOfLastUpdate'] + 1);

        try {
            $json = json_encode($contact, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the contact file.', previous: $exception);
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;
        if (@file_put_contents($path, $json, LOCK_EX) !== strlen($json)) {
            throw new RuntimeException('Unable to update the contact file.');
        }

        return $contact;
    }
}
