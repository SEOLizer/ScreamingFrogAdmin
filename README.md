# ScreamingFrog-Admin
With this script you determine the connection string to access the Screaming Frog's crawl database directly with other programs such as KNIME or DBeaver. You can also export crawls.

# SEOLizer
The code is provided to you by [SEOLizer](https://www.seolizer.de).

## Installation
- Install PHP on your system
  - MAC:
    - Install Homebrew "/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
    - Install PHP with "brew install php"
- clone this repository "clone https://github.com/SEOLizer/ScreamingFrogAdmin.git"

## Require
This library based on PHP (https://www.php.net/).

## Filelist
- sfadmin.php (Script with mainfunction)

## Use the Script
- Open a terminal and enter the following command "php sfadmin.php"
- As an output you get a list of the crawls in your ScreamingFrog
- The script is now waiting for your input. Enter the number in front of your desired crawl and press enter.
- The script then returns the data from the crawl.
