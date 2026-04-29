<?php

require __DIR__.'/../src/Renderer.php';

$r = new Renderer();

$out = $r->render('Hello {{ name }}!', ['name' => 'world']);
if ('Hello world!' !== $out) {
    fwrite(\STDERR, "Expected 'Hello world!', got: $out\n");
    exit(1);
}

// Missing placeholder must produce an empty string, not an error.
$out = $r->render('Hello {{ name }}, your token is {{ token }}.', ['name' => 'world']);
if ('Hello world, your token is .' !== $out) {
    fwrite(\STDERR, "Expected graceful fallback for missing key, got: $out\n");
    exit(1);
}

echo "ok\n";
exit(0);
