<?php

namespace Cherry\ActivityPub;

class ActivityPub
{
    const PERSON = 'Person';
    const GROUP = 'Group';
    const NOTE = 'Note';
    const QUESTION = 'Question';
    const ARTICLE = 'Article';
    const TOMBSTONE = 'Tombstone';

    const CREATE = 'Create';
    const DELETE = 'Delete';
    const ANNOUNCE = 'Announce';
    const UPDATE = 'Update';
    const LIKE = 'Like';
    const DISLIKE = 'Dislike';
    const FOLLOW = 'Follow';
    const ACCEPT = 'Accept';
    const REJECT = 'Reject';
    const UNDO = 'Undo';
}