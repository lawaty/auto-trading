<?php

abstract class StudentsOnly extends Authenticated
{
    public function prehandle(): ?Response
    {
        parent::prehandle();

        if (!$this->user instanceof Student)
            return new Response("Students only", 403);
        else
            return null;
    }
}
