<?php

abstract class UsersOnly extends Authenticated
{

    public function prehandle(): ?Response
    {
        parent::prehandle();

        if (!$this->user instanceof User)
            return new Response("Teachers only", 403);
        else
            return null;
    }
}
