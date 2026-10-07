<?php

namespace App\Console\Commands;

use App\Services\Greatday\FirebaseService;
use App\Support\Greatday\GreatdayAppData;
use App\Support\Greatday\NotificationCopy;
use Illuminate\Console\Command;

/**
 * Uji saluran notifikasi Greatday (in-app + MQTT + FCM) tanpa tinker.
 */
class SendGreatdayTestNotificationCommand extends Command
{
    protected $signature = 'greatday:test-notification
                            {--user-id=* : ID karyawan penerima (bisa dipanggil berkali-kali)}
                            {--user-ids= : Alternatif: 127 atau 127,128}
                            {--title= : Judul notifikasi}
                            {--body= : Isi notifikasi}
                            {--url= : Deep link di app Greatday}
                            {--dry-run : Hanya tampilkan payload, tidak kirim}';

    protected $description = 'Kirim notifikasi uji Greatday ke user_id (DB + MQTT + FCM)';

    public function handle(): int
    {
        $userIds = $this->resolveUserIds();
        if ($userIds === []) {
            $this->error('Wajib tentukan penerima: --user-id=127 atau --user-ids=127,128');

            return 1;
        }

        $title = trim((string) $this->option('title'));
        if ($title === '') {
            $title = '[TEST] Greatday notification';
        }

        $body = trim((string) $this->option('body'));
        if ($body === '') {
            $body = 'Tes saluran notifikasi dari artisan (local/Docker). Abaikan jika bukan target uji.';
        }

        $url = trim((string) $this->option('url'));
        if ($url === '') {
            $url = NotificationCopy::pathHome();
        }

        $payload = [
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ];

        $this->info('Payload:');
        $this->table(['Field', 'Value'], [
            ['title', $payload['title']],
            ['body', $payload['body']],
            ['url', $payload['url']],
        ]);

        $this->line('Penerima user_id: ' . implode(', ', $userIds));

        $tokenCount = GreatdayAppData::fcmTokenQuery()->whereIn('user_id', $userIds)->count();
        $this->line("Token FCM terdaftar (target): {$tokenCount}");

        if ($this->option('dry-run')) {
            $this->warn('Dry-run — tidak ada yang dikirim.');

            return 0;
        }

        try {
            $result = (new FirebaseService())->sendNotifications($userIds, $payload);
        } catch (\Throwable $e) {
            $this->error('Gagal mengirim: ' . $e->getMessage());
            $this->line('Credential: salin ke lumen-v3/storage/app/firebase/google-service.json');
            $this->line('Atau set .env FIREBASE_CREDENTIALS_PATH=/var/www/intilab-internal/storage/app/firebase/google-service.json');

            return 1;
        }

        if (!empty($result['summary']) && is_array($result['summary'])) {
            $this->info('Ringkasan FCM:');
            foreach ($result['summary'] as $key => $value) {
                $this->line("  {$key}: {$value}");
            }
        }

        $this->info($result['message'] ?? 'Selesai.');
        $this->line('Verifikasi: tabel gd_notification + bell /notifications di app (login sebagai penerima).');

        return !empty($result['success']) ? 0 : 1;
    }

    /**
     * @return list<int>
     */
    private function resolveUserIds(): array
    {
        $ids = [];

        foreach ((array) $this->option('user-id') as $raw) {
            $part = $this->parseIdList((string) $raw);
            $ids = array_merge($ids, $part);
        }

        $csv = trim((string) $this->option('user-ids'));
        if ($csv !== '') {
            $ids = array_merge($ids, $this->parseIdList($csv));
        }

        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));

        return $ids;
    }

    /**
     * @return list<int>
     */
    private function parseIdList(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $piece) {
            $id = (int) $piece;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return $out;
    }
}
