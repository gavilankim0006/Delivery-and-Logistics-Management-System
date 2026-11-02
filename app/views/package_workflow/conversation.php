<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
$handoffId = (int)($handoff['id'] ?? 0);
redirect('package-workflow' . ($handoffId > 0 ? '?c=' . $handoffId : ''));
