<?php namespace ProjectonistTests\Unit;

use Projectionist\Adapter\EventStream;
use Projectionist\Config;
use Projectionist\Adapter\EventStore;
use Projectionist\Services\ProjectorException;
use Projectionist\Strategy\EventHandler;
use Projectionist\Strategy\EventHandler\ClassName;
use Projectionist\Adapter\ProjectorPositionLedger;
use Projectionist\ValueObjects\ProjectorPosition;
use Projectionist\ValueObjects\ProjectorReference;
use Projectionist\Projectionist;
use Projectionist\ValueObjects\ProjectorReferenceCollection;
use ProjectonistTests\Fakes\Projectors\BrokenProjector;
use ProjectonistTests\Fakes\Services\EventStore\ThingHappened;
use Prophecy\Argument;

class ProjectionistTest extends \PHPUnit_Framework_TestCase
{
    // TODO: Move to own Projectorlayer test class
    // TODO: Clean this up, too much messy logic
    public function test_broken_projectors_are_marked_as_broken_and_the_error_is_bubbled_up()
    {
        $player = new ClassName();
        $position_ledger = $this->prophesize(ProjectorPositionLedger::class);

        $broken_projector = new BrokenProjector();

        $ref = ProjectorReference::makeFromProjector($broken_projector);
        $projector_position = ProjectorPosition::makeNewUnplayed($ref);
        $position_ledger->fetch($ref)->willReturn(null);
        $position_ledger->store($projector_position->broken())->shouldBeCalled();

        $event = new ThingHappened('');

        $adapter = $this->makeAdapter($player, $position_ledger->reveal(), [$event]);

        $projector_refs = ProjectorReferenceCollection::fromProjectors([$broken_projector]);

        $projectionist = new Projectionist($adapter, $projector_refs);

        $this->expectException(ProjectorException::class);

        $projectionist->play();
    }

    public function test_ignores_broken_projectors()
    {
        $player = $this->prophesize(EventHandler::class);
        $position_ledger = $this->prophesize(ProjectorPositionLedger::class);

        $ref = ProjectorReference::makeFromProjector(new BrokenProjector);
        $position = ProjectorPosition::makeNewUnplayed($ref)->broken();
        $position_ledger->fetch($ref)->willReturn($position);

        $adapter = $this->makeAdapter($player->reveal(), $position_ledger->reveal());

        $projectionist = new Projectionist($adapter, ProjectorReferenceCollection::fromProjectors([new BrokenProjector]));

        $projectionist->play();

        $player->handle(Argument::cetera())->shouldNotHaveBeenCalled();
    }

    private function makeAdapter(EventHandler $player, ProjectorPositionLedger $ledger, $events=[]): Config
    {
        $adapter = $this->prophesize(Config::class);

        $event_stream = new EventStream\InMemory($events);

        $event_store = $this->prophesize(EventStore::class);
        $event_store->getStream("")->willReturn($event_stream);

        $adapter->eventHandler()->willReturn($player);
        $adapter->projectorPositionLedger()->willReturn($ledger);
        $adapter->eventStore()->willReturn($event_store);

        return $adapter->reveal();
    }
}