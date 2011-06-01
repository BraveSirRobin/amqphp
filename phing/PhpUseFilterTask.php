<?php

require_once "phing/Task.php";

/**
 * This task  is designed to  normalise and correct use  statements in
 * composed nspf  files.  Nsp files are created  by concatenating many
 * different  per-class files,  each  of these  may  have contain  use
 * statements  that are  valid  within  the context  of  one file  but
 * invalid in  the NSPF  file.  We use  the token_get_all()  method to
 * filter out  and de-duplicate use statements, then  we re-insert the
 * single use statement and remove the individuals.
 */
class PhpUseFilterTask extends Task {

    /**
     * The file passed in the buildfile.
     */
    private $file = null;

    /**
     * The directory which was scanned before calling this task
     */
    private $dir = null;

    function setFile ($file) {
        $this->file = $file;
    }

    function setFromDir ($dir) {
        $this->dir = $dir;
    }

    function init () { }

    /**
     * The main entry point.
     */
    function main () {
        // Make sure  that the target  dir contains at least  one code
        // file, if not remove the target file as it's pointless.
        $hasFile = false;
        foreach (scandir($this->dir) as $item) {
            $item = "{$this->dir}/{$item}";
            if (substr($item, -4) == '.php' && is_file($item)) {
                $hasFile = true;
                break;
            }
        }
        if (! $hasFile) {
            printf("Remove empty NSPF package %s", $this->file);
            unlink($this->file);
            return;
        }

        $toks = token_get_all(file_get_contents($this->file));
        $uses = $this->extractUses($toks);
        if (! $uses) {
            return;
        }
        file_put_contents($this->file, $this->replacesUses($toks, $uses));
    }


    private function replacesUses ($toks, $use) {
        $buff = '';
        $uFlag = false;
        $tKeys = array_keys($toks);
        $C = count($toks);
        $level = 0;
        for ($i = 0; $i < $C; $i++) {
            if ((is_string($toks[$i]) && $toks[$i] == '{') ||
                (is_array($toks[$i]) &&
                 ($toks[$i][0] == T_CURLY_OPEN || $toks[$i][0] == T_DOLLAR_OPEN_CURLY_BRACES || $toks[$i][0] == T_STRING_VARNAME))) {
                $level++;
            }
            if (is_string($toks[$i]) && $toks[$i] == '}') {
                $level--;
            }

            if (is_string($toks[$i])) {
                $buff .= $toks[$i];
            } else if ($level == 0 && $toks[$i][0] == T_USE) {
                /**
                 * Found  a  root  level  use  statement,  insert  the
                 * replacement (if required) then throw away remaining
                 * tokens until the next semi-colon
                 */
                if (! $uFlag) {
                    $buff .= $use;
                    $uFlag = true;
                }
                do {
                    $i++;
                } while (! (is_string($toks[$i]) && $toks[$i] == ';'));
                $uFlag = true;
            } else {
                $buff .= $toks[$i][1];
            }
        }
        return $buff;
    }



    /**
     * Extract the use  statements from $toks, normalise, de-duplicate
     * and return as a single compound use statement.
     */
    private function extractUses ($toks) {
        $flag = false;
        $uses = array();
        $use = '';
        $level = 0;
        foreach ($toks as $tok) {
            if ((is_string($tok) && $tok == '{') ||
                (is_array($tok) &&
                 ($tok[0] == T_CURLY_OPEN || $tok[0] == T_DOLLAR_OPEN_CURLY_BRACES || $tok[0] == T_STRING_VARNAME))) {
                $level++;
            }
            if (is_string($tok) && $tok == '}') {
                $level--;
            }

            if (is_array($tok) && $tok[0] == T_USE && $level == 0) {
                $flag = true;
                //$use = $tok[1];
            } else if ($flag) {
                if (is_string($tok)) {
                    if ($tok == ';') {
                        // Exit condition for this 'use'.  Split out multiple aliases at this point.
                        if (false !== strpos($use, ',')) {
                            foreach (explode(',', $use) as $i => $part) {
                                $uses[] = trim(strtolower($part));
                            }
                        } else {
                            $uses[] = trim(strtolower($use));
                        }
                        $use = '';
                        $flag = false;
                    } else {
                        $use .= $tok;
                    }
                } else {
                    $use .= $tok[1];
                }
            }
        }
        if (! $uses) {
            return '';
        }
        $uses = array_unique($uses);
        return 'use ' . implode(', ', $uses) . ';';
    }
}