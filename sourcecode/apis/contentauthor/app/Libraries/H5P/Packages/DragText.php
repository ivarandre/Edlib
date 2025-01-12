<?php

namespace App\Libraries\H5P\Packages;


class DragText extends H5PBase
{

    public static $machineName = "H5P.DragText";
    protected $majorVersion = 1;
    protected $minorVersion = 8;

    public function getPackageSemantics()
    {
        // TODO: Implement getPackageSemantics() method.
    }

    public function populateSemanticsFromData($data)
    {
        // TODO: Implement populateSemanticsFromData() method.
    }

    public function getElements(): array
    {
        // TODO: Implement getElements() method.
    }

    public function getAnswers($index = null)
    {
        // TODO: Implement getAnswers() method.
    }

    public function getPackageAnswers($data)
    {
        // TODO: Implement getPackageAnswers() method.
    }

    protected function alterAutoCheck()
    {
        collect($this->packageStructure)
            ->filter(function ($values, $key) {
                return strtolower($key) === "behaviour" && property_exists($values, "instantFeedback");
            })
            ->transform(function ($values) {
                $values->instantFeedback = $this->behaviorSettings->autoCheck;
                return $values;
            })
            ->toArray();
    }
}