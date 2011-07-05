Amqphp build code readme.
------------------------

Author: Robin Harvey (harvey.robin@gmail.com)
Copyright: Robin Harvey (harvey.robin@gmail.com)

This directory contains 2 copies of the Amqphp code, the `nspf` folder
(namespace per file) places all  classes (and interfaces) in to single
files according  to their  namespace.  This means  that a  single file
contains defines  a complete namespace.   Classes in the  `cpf` folder
are placed  in their own  files with the  file named after  the class,
this is  very common and  can be used  by popular class  loaders.  Sub
directories are mapped to sub-namespaces.

All of  the code in  this directory is  compressed by default,  if you
want  to view  commented code,  check  the /src  directory.  To  build
yourself,  install phing and  configure the  build properties  to suit
your needs, please note the build process is not tested on Windows, as
I don't have a Windows machine to test on (let me you if you manage to
get this working).
