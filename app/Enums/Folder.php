<?php

namespace App\Enums;

/** Letak utas. Terkirim/Draf/Terjadwal/Berbintang adalah tampilan turunan, bukan folder. */
enum Folder: string
{
    case Inbox = 'inbox';
    case Archive = 'archive';
    case Spam = 'spam';
    case Trash = 'trash';
}
