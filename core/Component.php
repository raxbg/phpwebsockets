<?hh
abstract class Component {

	public function __construct(
        protected Server $server
    ){}
	
	abstract public function onMessage(int $client_id, string $data, string $dataType): bool;
}
