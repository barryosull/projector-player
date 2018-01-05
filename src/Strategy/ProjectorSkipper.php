<?php namespace Projectionist\Strategy;

use Projectionist\Adapter\EventWrapper;
use Projectionist\Config;
use Projectionist\ValueObjects\ProjectorReference;
use Projectionist\ValueObjects\ProjectorReferenceCollection;
use Projectionist\ValueObjects\ProjectorPosition;

class ProjectorSkipper
{
    private $projector_position_ledger;
    private $event_store;

    public function __construct(Config $adapter)
    {
        $this->projector_position_ledger = $adapter->projectorPositionLedger();
        $this->event_store = $adapter->eventStore();
    }

    public function skip(ProjectorReferenceCollection $projector_references)
    {
        if (!$this->event_store->hasEvents()) {
            return;
        }

        $latest_event = $this->event_store->latestEvent();
        foreach ($projector_references as $projector_reference) {
            $this->skipProjectorToEvent($projector_reference, $latest_event);
        }
    }

    private function skipProjectorToEvent(ProjectorReference $projector_reference, EventWrapper $latest_event)
    {
        $projector_position = $this->projector_position_ledger->fetch($projector_reference);
        if (!$projector_position) {
            $projector_position = ProjectorPosition::makeNewUnplayed($projector_reference);
        }
        if ($latest_event) {
            $projector_position = $projector_position->played($latest_event);
        }

        $this->projector_position_ledger->store($projector_position);
    }
}