<?php

namespace App\Libraries\H5P\Packages;


class DragQuestion extends H5PBase
{

    public static $machineName = "H5P.DragQuestion";
    protected $majorVersion = 1;
    protected $minorVersion = 11;

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

    public function alterSemantics(&$semantics)
    {
        if (config('h5p.H5P_DragQuestion.disableFullscreen', false) === true) {
            $this->disableFullscreen($semantics);
        }
    }

    private function disableFullscreen(&$semantics)
    {
        collect($semantics)
            ->filter(function ($semantic) {
                return strtolower($semantic->name) === "behaviour";
            })
            ->pluck('fields')
            ->flatten()
            ->filter(function ($field) {
                return strtolower($field->name) === "enablefullscreen";
            })
            ->transform(function ($field) {
                $field->widget = "showWhen";
                $field->showWhen = (object)[
                    'detach' => true,
                    'rules' => [
                    ]
                ];
                return $field;
            })
            ->toArray();
    }

    public function getPackageAnswers($data)
    {
        // TODO: Implement getPackageAnswers() method.
    }
}