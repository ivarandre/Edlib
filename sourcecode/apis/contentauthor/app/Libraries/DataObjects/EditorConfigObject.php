<?php


namespace App\Libraries\DataObjects;


use Cerpus\Helper\Traits\CreateTrait;

/**
 * Class EditorConfigObject
 * @package App\Libraries\DataObjects
 *
 * @method static EditorConfigObject create($attributes = null)
 */
class EditorConfigObject
{
    use CreateTrait;

    public $useDraft, $canPublish, $canList, $useLicense = false;

    protected $contentProperties;

    public $locked = false;
    public $pulseUrl = null;

    protected $lockedProperties;

    public function setContentProperties(ResourceInfoDataObject $infoDataObject) {
        $this->contentProperties = $infoDataObject->toArray();
    }

    public function setLockedProperties(LockedDataObject $lockedDataObject) {
        $this->locked = true;
        $this->lockedProperties = $lockedDataObject->toArray();
    }

    /**
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
