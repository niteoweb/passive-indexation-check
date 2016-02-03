# Passive Indexation Check

[![Build Status](https://travis-ci.com/niteoweb/passive-indexation-check.svg?token=MFHqF1ZX1qpAAAur9Z9s&branch=master)](https://travis-ci.com/niteoweb/passive-indexation-check)
[![Coverage Status](https://coveralls.io/repos/github/niteoweb/passive-indexation-check/badge.svg?branch=master)](https://coveralls.io/github/niteoweb/passive-indexation-check?branch=master)

## Info

Contributors: niteoweb
Tags: seo, indexation, googlebot, index
Requires at least: 4.0
Tested up to: 4.4.1
Stable tag: 1.0

Passive Indexation Check notifies you when googlebot stops visiting your blog for a certain period of time.

## Description

Passive Indexation Check notifies you when googlebot stops visiting your blog for a certain period of time.

## Plugin Features
* Send notifications when googlebot stops visiting.
* Add multiple emails for notifications.

## Installation

1. Upload 'passive-indexation-check' directory to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Settings** menu and then **Passive Indexation Check** to configure
# Dev Notes

## Tests

 - Tests should go into tests/folder and subclass PHPUnit_Framework_TestCase and
use WP_Mock.
 - Code Coverage must be at all times 100% if there is some part of
 code that cannot be tested it should be rewritten
 - Test and code must follow PSR-2.

## Running

- Install composer and initialize project
- `make lint && make test`
