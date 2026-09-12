Templates
=========

You can customize the global layout by tweaking the ``AdminataBundle`` configuration.

.. code-block:: yaml

    # config/packages/adminata.yaml

    adminata:
        templates:
            # default global templates
            layout:  '@Adminata/standard_layout.html.twig'
            ajax:    '@Adminata/ajax_layout.html.twig'

            # default value if done set, actions templates, should extend global templates
            list:    '@Adminata/CRUD/list.html.twig'
            show:    '@Adminata/CRUD/show.html.twig'
            edit:    '@Adminata/CRUD/edit.html.twig'

You can also configure the templates used by the Form Framework while rendering the widget

.. code-block:: yaml

    # config/packages/adminata_doctrine_mongodb.yaml

    adminata_doctrine_mongodb:
        templates:
            form: ['@AdminataDoctrineMongoDB/Form/form_admin_fields.html.twig']
            filter: ['@AdminataDoctrineMongoDB/Form/filter_admin_fields.html.twig']

You can also customize field types

.. code-block:: yaml

    # config/packages/adminata_doctrine_mongodb.yaml

    adminata_doctrine_mongodb:
        templates:
        types:
            list:
                date:     '@Adminata/CRUD/date_field.html.twig'
                datetime: '@Adminata/CRUD/datetime_field.html.twig'

.. note::

    By default, if the ``SonataIntlBundle`` classes are availables, then the numeric and date fields will be
    localized with the current user locale (only for list, work in progress).

You can also customize field types by adding types in the ``adminata_doctrine_mongodb.yaml`` file. The default values are :

.. code-block:: yaml

    # config/packages/adminata_doctrine_mongodb.yaml

    adminata_doctrine_mongodb:
        templates:
            types:
                list:
                    array:      '@Adminata/CRUD/list_array.html.twig'
                    boolean:    '@Adminata/CRUD/list_boolean.html.twig'
                    date:       '@Adminata/CRUD/list_date.html.twig'
                    time:       '@Adminata/CRUD/list_time.html.twig'
                    datetime:   '@Adminata/CRUD/list_datetime.html.twig'
                    text:       '@Adminata/CRUD/base_list_field.html.twig'
                    trans:      '@Adminata/CRUD/list_trans.html.twig'
                    string:     '@Adminata/CRUD/base_list_field.html.twig'
                    smallint:   '@Adminata/CRUD/base_list_field.html.twig'
                    bigint:     '@Adminata/CRUD/base_list_field.html.twig'
                    integer:    '@Adminata/CRUD/base_list_field.html.twig'
                    decimal:    '@Adminata/CRUD/base_list_field.html.twig'
                    identifier: '@Adminata/CRUD/base_list_field.html.twig'

                show:
                    array:      '@Adminata/CRUD/show_array.html.twig'
                    boolean:    '@Adminata/CRUD/show_boolean.html.twig'
                    date:       '@Adminata/CRUD/show_date.html.twig'
                    time:       '@Adminata/CRUD/show_time.html.twig'
                    datetime:   '@Adminata/CRUD/show_datetime.html.twig'
                    text:       '@Adminata/CRUD/base_show_field.html.twig'
                    trans:      '@Adminata/CRUD/show_trans.html.twig'
                    string:     '@Adminata/CRUD/base_show_field.html.twig'
                    smallint:   '@Adminata/CRUD/base_show_field.html.twig'
                    bigint:     '@Adminata/CRUD/base_show_field.html.twig'
                    integer:    '@Adminata/CRUD/base_show_field.html.twig'
                    decimal:    '@Adminata/CRUD/base_show_field.html.twig'

.. note::

    By default, if the ``SonataIntlBundle`` classes are available, then the numeric
    and date fields will be localized with the current user locale.
