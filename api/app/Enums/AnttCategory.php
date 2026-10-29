<?php

namespace App\Enums;

/**
 * A categoria do transportador no RNTRC da ANTT.
 */
enum AnttCategory: string
{
    case Tac = 'tac';
    case Etc = 'etc';
    case Ctc = 'ctc';
}
