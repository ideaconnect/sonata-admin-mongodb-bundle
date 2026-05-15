# idct/sonata-admin-mongodb-bundle

Integrate Doctrine MongoDB ODM into the Sonata Admin Bundle.

Started as a fork of [sonata-project/doctrine-mongodb-admin-bundle](https://github.com/sonata-project/SonataDoctrineMongoDBAdminBundle),
modernised for **PHP 8.4+**, **Symfony 7.3 / 7.4 / 8.0**, **doctrine/persistence 3 & 4**,
**doctrine/mongodb-odm 2.6+** and **doctrine/mongodb-odm-bundle 5.x**.

> ### ⚠️ Heads up: this fork will diverge from upstream
>
> This package is **not** a drop-in replacement for `sonata-project/doctrine-mongodb-admin-bundle`
> and will not stay backwards-compatible with it.
>
> The 5.x line already breaks BC in places upstream has not (private `ModelManager::getDocumentManager`,
> immutable-ish `ProxyQuery`, dropped `__call` magic, stricter type guards in `ModelFilter`, etc.),
> and future releases will keep diverging — extending the public API, replacing parts that aren't
> worth keeping, and dropping things upstream still ships. If you need an exact upstream-compatible
> integration, stay on `sonata-project/doctrine-mongodb-admin-bundle`. If you want the modernized
> base and don't mind moving with us, you're in the right place.

Branch | Github Actions | Code Coverage |
------ | -------------- | ------------- |
5.x | [![Test][test_badge]][test_link] | [![Coverage Status][coverage_badge]][coverage_link] |

## Installation

```bash
composer require idct/sonata-admin-mongodb-bundle
```

## Documentation

Upstream Sonata documentation still applies for the public API and configuration shape:
[docs.sonata-project.org/projects/SonataDoctrineMongoDBAdminBundle](https://docs.sonata-project.org/projects/SonataDoctrineMongoDBAdminBundle).

Fork-specific changes (modernisation, performance and correctness fixes, BC notes for the 5.x
cut) are tracked in [BEST_VERSION.md](BEST_VERSION.md) and [CHANGELOG.md](CHANGELOG.md).

## Continuous integration

Pushes and pull requests run the full PHPUnit suite on the [5.x test workflow][test_link]
across the supported PHP × Symfony matrix and upload coverage to
[Codecov][coverage_link]. Coverage thresholds and ignored paths live in
[`codecov.yml`](codecov.yml).

**One-time setup for maintainers:** Codecov requires a project token for uploads from forks.
Create one at [codecov.io](https://app.codecov.io/) and add it to the GitHub repo under
`Settings → Secrets and variables → Actions` as `CODECOV_TOKEN`. The workflow already wires
it into the upload step.

## Running the tests locally

The test suite needs MongoDB and (for the Panther functional tests) a Firefox WebDriver.
Both are wired up via [docker-compose.yml](docker-compose.yml):

```bash
docker compose up -d
PANTHER_SELENIUM_HOST=http://127.0.0.1:4444/wd/hub make test
```

Selenium also exposes noVNC at `http://127.0.0.1:7900` (password `secret`) if you want to watch
the browser drive the suite.

## Support

For bugs or feature ideas in this fork, please open an issue on
[the fork's GitHub repository](https://github.com/ideaconnect/sonata-admin-mongodb-bundle/issues).

For questions about Sonata Admin in general, the upstream
[StackOverflow tag](https://stackoverflow.com/questions/tagged/sonata) remains the best place.

## License

This package is available under the [MIT license](LICENSE). The original copyright by
Thomas Rabaix and every upstream contributor is preserved; see [composer.json](composer.json)
for the complete author roster.

[test_badge]: https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/test.yaml/badge.svg?branch=5.x
[test_link]: https://github.com/ideaconnect/sonata-admin-mongodb-bundle/actions/workflows/test.yaml?query=branch:5.x
[coverage_badge]: https://codecov.io/gh/ideaconnect/sonata-admin-mongodb-bundle/branch/5.x/graph/badge.svg
[coverage_link]: https://app.codecov.io/gh/ideaconnect/sonata-admin-mongodb-bundle/tree/5.x
