<?php

namespace App\Libraries\H5P\Packages;

class MemoryGame extends H5PBase
{
    public static $machineName = "H5P.MemoryGame";
    protected $majorVersion = 1;
    protected $minorVersion = 2;

    protected $canExtractAnswers = false;

    public function getElements(): array
    {
        // TODO: Implement getElements() method.
    }

    public function getAnswers($index = null)
    {
        // TODO: Implement getAnswers() method.
    }

    public function populateSemanticsFromData($data)
    {
    }

    public function getPackageSemantics()
    {
        // TODO: Traverse the semantics.json in the actual directory for multiplechoice
        return json_decode('{"cards":[{},{}],"behaviour":{"useGrid":true,"allowRetry":true},"lookNFeel":{"themeColor":"#909090"},"l10n":{"cardTurns":"Card turns","timeSpent":"Time spent","feedback":"Good work!","tryAgain":"Reset","closeLabel":"Close","label":"Memory Game.\u00a0Find the matching cards.","done":"All of the cards have been found.","cardPrefix":"Card %num:","cardUnturned":"Unturned.","cardMatched":"Match found."}}');
    }


    public function getPackageAnswers($data)
    {
        // TODO: Implement getPackageAnswers() method.
    }

    protected function alterRetryButton()
    {
        collect($this->packageStructure)
            ->filter(function ($values, $key) {
                return strtolower($key) === "behaviour" && property_exists($values, "allowRetry");
            })
            ->transform(function ($values) {
                $values->allowRetry = $this->behaviorSettings->enableRetry;
                return $values;
            })
            ->toArray();
    }
}