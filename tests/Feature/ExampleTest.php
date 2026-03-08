<?php

it('returns the service root payload', function (): void {
    $response = $this->get('/');

    $response
        ->assertOk()
        ->assertJsonPath('service', config('app.name'))
        ->assertJsonPath('status', 'ok');
});
