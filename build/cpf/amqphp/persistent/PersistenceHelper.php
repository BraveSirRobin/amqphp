<?php
 namespace amqphp\persistent; interface PersistenceHelper { function setUrlKey ($k); function getData (); function setData ($data); function save (); function load (); function destroy (); }