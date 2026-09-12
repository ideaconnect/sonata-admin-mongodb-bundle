Doctrine MongoDB Proxy Query
============================

The ``ProxyQuery`` object is used to add missing features from the original Doctrine Query builder::

    use IDCT\Adminata\DoctrineMongoDB\Datagrid\ProxyQuery;

    $queryBuilder = $this->em->createQueryBuilder();

    $proxyQuery = new ProxyQuery($queryBuilder);
    $proxyQuery->setSortBy('name');
    $proxyQuery->setMaxResults(10);

    $results = $proxyQuery->execute();
