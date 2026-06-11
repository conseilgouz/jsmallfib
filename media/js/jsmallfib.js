
function confirmDelfolder(baselink, dir, delfolder, msgString) {

    if (confirm(msgString)) {
        window.location=baselink+'&dir='+escape(dir)+'&delfolder='+escape(delfolder); // treat others like IE
        return;
    }
}
function confirmDelfile(baselink, dir, delfile, msgString) {

    if (confirm(msgString)) {
       window.location=baselink+'&dir='+escape(dir)+'&delfile='+escape(delfile); // treat others like IE
    }
    return;
}
        
function confirmExtractfile(baselink, dir, extfile, msgString) {

    if (confirm(msgString)) {
       window.location=baselink+'&dir='+escape(dir)+'&extfile='+escape(extfile); // treat others like IE
       return;
    }
}
function confirmRestoreFile(baselink, dir, restorefile, msgString) {

    if (confirm(msgString)) {
        window.location=baselink+'&dir='+escape(dir)+'&restorefile='+escape(restorefile);// treat others like IE
        return;
    }
}

