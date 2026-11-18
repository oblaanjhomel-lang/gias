<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'category' => $data['category'] ?? 'system',
            'message' => $data['message'] ?? '',
            'action_path' => $data['action_path'] ?? null,
            'action_label' => $data['action_label'] ?? null,
            'read' => $this->read_at !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_human' => $this->created_at ? self::ago($this->created_at) : null,
        ];
    }

    /** "just now", "5 min ago", "2 h ago", "3 d ago", then "12 Nov 2026" (Manila). */
    public static function ago(CarbonInterface $at): string
    {
        $seconds = max(0, now()->getTimestamp() - $at->getTimestamp());

        return match (true) {
            $seconds < 60 => 'just now',
            $seconds < 3600 => intdiv($seconds, 60).' min ago',
            $seconds < 86400 => intdiv($seconds, 3600).' h ago',
            $seconds < 7 * 86400 => intdiv($seconds, 86400).' d ago',
            default => $at->copy()->setTimezone(config('gias.local_timezone'))->format('j M Y'),
        };
    }
}
