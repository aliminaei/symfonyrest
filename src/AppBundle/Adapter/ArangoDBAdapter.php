<?php
    
namespace AppBundle\Adapter;

use triagens\ArangoDb\CollectionHandler as CollectionHandler;
use triagens\ArangoDb\DocumentHandler as DocumentHandler;
use triagens\ArangoDb\EdgeHandler as EdgeHandler;
use triagens\ArangoDb\GraphHandler as GraphHandler;
use triagens\ArangoDb\Graph as Graph;
use triagens\ArangoDb\EdgeDefinition as EdgeDefinition;
use triagens\ArangoDb\ConnectionOptions as ConnectionOptions;
use triagens\ArangoDb\UpdatePolicy as UpdatePolicy;
use triagens\ArangoDb\Connection as Connection;
use triagens\ArangoDb\Collection as Collection;
use triagens\ArangoDb\Document as Document;
use triagens\ArangoDb\Edge as Edge;
use triagens\ArangoDb\Statement as Statement;
use \Exception as Exception;

use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * A singleton class that handles connections, queries and transactions with ArangoDB - https://www.arangodb.com/
 * 
 * This class uses ArangoDB PHP client pacckage
 * 
 * Note - ArangoDB client uses ArangoDB HTTP API to connect to the database so we do not need to 
 * establish a persistent connection and keep its state.
 * Instead, connections are established on the fly for each request and are destroyed afterwards.
 * 
 */
class ArangoDBAdapter
{

    protected static $instance = null;

    private $container;    
    private $connection;
    private $collectionHandler;
    private $documentHandler;
    private $edgeHandler;
    private $graphHandler;
    private $contributorsGraph;

    private $contributorsGraphName = "ContributorsGraph";
    private $gitUsers = "GitUsers";
    private $packages = "Packages";
    private $contributedTo = "ContributedTo";

    /**
     * Set up the connection object, the handlers t owork with the database and get or create the Contributors Graph
     *
     */
    protected function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $connectionOptions = array(
            // server endpoint to connect to
            ConnectionOptions::OPTION_ENDPOINT => $this->container->getParameter('arangoDB.endpoint'),
            // authorization type to use (currently supported: 'Basic')
            ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
            // user for basic authorization
            ConnectionOptions::OPTION_AUTH_USER => $this->container->getParameter('arangoDB.user'),
            // password for basic authorization
            ConnectionOptions::OPTION_AUTH_PASSWD => $this->container->getParameter('arangoDB.password'),
            // connection persistence on server. can use either 'Close' (one-time connections) or 'Keep-Alive' (re-used connections)
            ConnectionOptions::OPTION_CONNECTION => 'Close',
            // connect timeout in seconds
            ConnectionOptions::OPTION_TIMEOUT => 3,
            // whether or not to reconnect when a keep-alive connection has timed out on server
            ConnectionOptions::OPTION_RECONNECT => true,
            // optionally create new collections when inserting documents
            ConnectionOptions::OPTION_CREATE => true,
            // optionally create new collections when inserting documents
            ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
        );
         
        // open connection
        $this->connection = new Connection($connectionOptions);

        $this->collectionHandler = new CollectionHandler($this->connection);
        $this->documentHandler = new DocumentHandler($this->connection);
        $this->edgeHandler       = new EdgeHandler($this->connection);
        $this->graphHandler = new GraphHandler($this->connection);

        // Get the contributors graph or create it if it does not exist yet.
        if ($this->graphHandler->getGraph($this->contributorsGraphName) == false) 
        {
            //Create a new graph
            $this->contributorsGraph = new Graph();
            $this->contributorsGraph->set('_key', $this->contributorsGraphName);
            $this->contributorsGraph->addEdgeDefinition(EdgeDefinition::createUndirectedRelation($this->contributedTo, [$this->gitUsers, $this->packages]));
            $this->graphHandler->createGraph($this->contributorsGraph);
        }
        else
        {
            //Get the existing graph
            $this->contributorsGraph = $this->graphHandler->getGraph($this->contributorsGraphName);
        }

    }

    protected function __clone()
    {
    }

    protected function __wakeup()
    {
    }

    /**
     * Creates a connection object and make sure we only have one instance that connects to the database.
     *
     * @return Connection object
     *
     */
    public static function getInstance(ContainerInterface $container)
    {
        if (!isset(static::$instance))
        {
            static::$instance = new static($container);
        }
        return static::$instance;
    }

    /**
     *
     * @return the number of packages that we aleady have in the database.
     *
     */
    public function getPackagesCount()
    {
        if (!$this->collectionHandler->has($this->packages))
        {
            return 0;
        }

        return $this->collectionHandler->count($this->packages);
    }

    /**
     * Updates all the contributors for the given package name
     * 
     * @param  string $packageName  -  the package name 
     * @param  string $repoURL  -  the package's github repository URL 
     * @param  array $_contributors  -  the list of github users who contributed to the given package 
     *
     */
    public function updateContributors($packageName, $repoURL, $_contributors)
    {
        $contributorsCount = count($_contributors);


        $package = null;
        //Check if package exists already
        if ($this->documentHandler->has($this->packages, base64_encode($packageName)))
        {
            //Package exists already
            $package = $this->documentHandler->get($this->packages, base64_encode($packageName));
        }

        if($package != null)
        {
            if ($package->get("contributors_count") == $contributorsCount)
            {
                //Package exists and the number of contributirs has not changed!! Nothing to do here!
                // return;
            }
        }
        else
        {   
            //New Package - add the package to the database
            $package = $this->addPackage($packageName, $repoURL, count($_contributors));    
            // echo "Adding Package: ".$packageName." key: ".$package->getKey()."\n";
        }

        //iterate the contributors list and add all the new contributors to the package
        foreach ($_contributors as $_contributor)
        {
            //check if the contributr already exists in the database 
            if ($this->documentHandler->has($this->gitUsers, $_contributor))
            {
                //Existing contributor
                $contributor = $this->documentHandler->get($this->gitUsers, $_contributor);
                // echo "cont ".$_contributor." exist\n";
            }
            else
            {
                //New Contributors - Add to the database
                $contributor = $this->addContributor($_contributor);
                // echo "Adding cont: ".$_contributor." key: ".$contributor->getKey()."\n";
            }

            $edgeId = sprintf("%s__%s", $contributor->getId(), $package->getId());

            //Check if the contributor is already connected to this package 
            //We might have an existing contributor that has contributed to other packages but not this package
            if (!$this->documentHandler->has($this->contributedTo, $edgeId))
            {
                //There is no connection - Connect this contributor to the package
                // echo "Adding edge from: ".$_contributor." to: ".$packageName." key: ";
                $numberOfContributions = $contributor->get("number_of_contributions");
                $contributor->set("number_of_contributions", $numberOfContributions + 1);
                $this->documentHandler->update($contributor);
                $this->connectContributor($contributor, $package);
            }
            else
            {
                // echo "edge from: ".$_contributor." to: ".$packageName." key: ".$edgeId." already exists.\n";
            }
        }
        // echo "\n\n";
    }

    /**
     * Add a new package to the database.
     * We save the package repo URL so we dont have to query this everytime when we update the graph periodically, 
     * We are also saving the number of contributors so for the periodic updates we can easily check if there is a new contributor for this package!
     * 
     * @param  string $packageName  -  the package name 
     * @param  string $repoURL  -  the package's github repository URL 
     * @param  array $contributorsCount  -  the number github users who contributed to the given package 
     *
     * @return the created package.
     */
    private function addPackage($packageName, $repoURL, $contributorsCount)
    {
        $vertexArray = [
            '_key' => base64_encode($packageName),
            'name' => $packageName,
            'contributors_count' => $contributorsCount,
            'repo_url' => $repoURL
        ];

        $package = Document::createFromArray($vertexArray);
        $this->documentHandler->save($this->packages, $package);
        return $package;
    }

    /**
     * Add a new contributor to the database.
     * 
     * @param  string $contributorName  -  the contributor github username
     *
     * @return the created contributor.
     */
    private function addContributor($contributorName)
    {
        $vertexArray = [
            '_key'     => $contributorName,
            'username' => $contributorName,
            'number_of_contributions' => 1
        ];

        $contributor = Document::createFromArray($vertexArray);
        $this->documentHandler->save($this->gitUsers, $contributor);
        return $contributor;
    }

    /**
     * Connectio the contributor to the package.
     * 
     * @param  Contributor $contributor  -  the contributor handle
     * @param  Package $package  -  the package handle
     *
     */
    private function connectContributor($contributor, $package)
    {
        $edgeId = sprintf("%s__%s", $contributor->getId(), $package->getId());
        $edgeArray   = [
            '_key' => $edgeId
        ];

        $edge = Edge::createFromArray($edgeArray);
        $this->edgeHandler->saveEdge($this->contributedTo, $contributor->getHandle(), $package->getHandle(), $edge);
        // echo $edge->getKey()."\n";
    }

    /**
     * Retrives the shortest path between the two contributors.
     * ArangoDB has a builtin function/query that calculate the shortest path between two vertexes.
     * 
     * 
     * @param  Contributor $contributor1  -  the contributor1 github username
     * @param  Contributor $contributor2  -  the contributor2 github username
     *
     * @return the shortest path in json format.
     */
    public function getShortestPath($contributor1, $contributor2) 
    {
        $query = sprintf("FOR v IN ANY SHORTEST_PATH '%s/%s' TO '%s/%s' %s FILTER IS_SAME_COLLECTION(v, %s) RETURN v.name", 
            $this->gitUsers, $contributor1, $this->gitUsers, $contributor2,
            $this->contributedTo, $this->packages);

        $statement = new Statement(
            $this->connection,
            array(
                "query"     => $query,
                "count"     => true,
                "batchSize" => 1000,
                "sanitize"  => true
            )
        );

        $cursor = $statement->execute();
        $result = array();

        foreach ($cursor as $key => $value)
        {
            $result[$key] = $value;
        }

        return $result;
    }

    public function findTopPotentialContributors($packageName)
    {
        try
        {
            $package = $this->documentHandler->get($this->packages, base64_encode($packageName));    
        }
        catch (Exception $e) 
        {
            throw new Exception("Package not found.");          
        }
        

        $query = sprintf('FOR v in %s Filter CONCAT("%s/", v.username) not in (For e IN %s FILTER e._to == "%s" return e._from) sort v.number_of_contributions desc return {"name": v.username, "number_of_contributions": v.number_of_contributions}', 
            $this->gitUsers, $this->gitUsers,
            $this->contributedTo,
            $package->getHandle());

        $statement = new Statement(
            $this->connection,
            array(
                "query"     => $query,
                "count"     => true,
                "batchSize" => 1000,
                "sanitize"  => true
            )
        );

        $cursor = $statement->execute();

        $result = array();

        foreach ($cursor as $key => $value)
        {
            $result[$key] = $value;
            // echo $key.": ".$value."\n";
        }

        $responseString = "[".implode(",", $result)."]";

        return json_decode($responseString, true);

        // return $result;

    }
}
