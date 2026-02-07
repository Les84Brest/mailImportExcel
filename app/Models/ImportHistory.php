<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportHistory extends Model
{
    protected $table = 'import_histories';

    protected $fillable = [
        'mail_id',
        'filename',
        'completed_at',
        'error_message',
        'error_count',
        'deleted_count',
        'created_count',
        'total_items',
    ];

    public static function isMessageProcessed(string $msgId): bool
    {
        $messageIds = self::pluck('mail_id');

        return $messageIds->contains($msgId);
    }


}
