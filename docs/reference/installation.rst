Installation
============

AdminataDoctrineMongoDBBundle is part of a set of bundles aimed at abstracting
storage connectivity for AdminataBundle. As such, AdminataDoctrineMongoDBBundle
depends on AdminataBundle, and will not work without it.

.. note::

    These installation instructions are meant to be used only as part of AdminataBundle's
    installation process, which is documented `here <https://docs.sonata-project.org/projects/AdminataBundle/en/3.x/getting_started/installation/>`_.

Download the Bundle
-------------------

.. code-block:: bash

    composer require sonata-project/doctrine-mongodb-admin-bundle

Enable the Bundle
-----------------

Then, enable the bundle by adding it to the list of registered bundles
in ``bundles.php`` file of your project::

    // config/bundles.php

    return [
        // ...
        IDCT\Adminata\DoctrineMongoDB\AdminataDoctrineMongoDBBundle::class => ['all' => true],
    ];
