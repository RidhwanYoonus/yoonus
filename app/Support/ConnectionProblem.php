<?php

namespace App\Support;

use RuntimeException;

/** The server could not open a secure connection (network, firewall or certificate problem). */
class ConnectionProblem extends RuntimeException {}
