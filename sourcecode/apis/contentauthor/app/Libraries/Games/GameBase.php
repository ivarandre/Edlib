<?php

namespace App\Libraries\Games;

use Illuminate\Support\Facades\Storage;
use App\Gametype;
use App\Libraries\Games\Contracts\GameTypeContract;
use App\Traits\FractalTransformer;

abstract class GameBase implements GameTypeContract
{
    use FractalTransformer;

    public static $machineName;

    protected $majorVersion;
    protected $minorVersion;

    protected $maxScore;

    public static function customValidation($dataToBeValidated)
    {
        return true;
    }

    public function convertLanguageCode($languageCode)
    {
        switch (strtolower($languageCode)) {
            case 'nb-no':
            case 'nb_no':
                return 'nb_no';
            case 'en-gb':
            case 'en_gb':
            case 'en-us':
            case 'en_us':
            default:
                return 'en_us';
        }
    }

    public function getMaxScore()
    {
        return $this->maxScore;
    }
}
