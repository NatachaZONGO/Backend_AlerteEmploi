<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class NotificationMessage extends Model {
    protected $fillable = ['notification_id','sender_id','type','content','meta','replied_to_id'];
    protected $casts = ['meta'=>'array'];

    public function notification(){ return $this->belongsTo(Notification::class); }
    public function sender(){ return $this->belongsTo(User::class,'sender_id'); }
    public function repliedTo(){ return $this->belongsTo(NotificationMessage::class,'replied_to_id'); }
}
