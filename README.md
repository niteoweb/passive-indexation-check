# Passive Indexation Check

[![Build Status](https://travis-ci.com/niteoweb/passive-indexation-check.svg?token=MFHqF1ZX1qpAAAur9Z9s&branch=master)](https://travis-ci.com/niteoweb/passive-indexation-check)
[![Coverage Status](https://coveralls.io/repos/github/niteoweb/passive-indexation-check/badge.svg?branch=master)](https://coveralls.io/github/niteoweb/passive-indexation-check?branch=master)

## Tests

 - Tests should go into tests/folder and subclass PHPUnit_Framework_TestCase and
use WP_Mock.
 - Code Coverage must be at all times 100% if there is some part of
 code that cannot be tested it should be rewritten
 - Test and code must follow PSR-2.

## Running

- Install composer and initialize project
- `make lint && make test`
