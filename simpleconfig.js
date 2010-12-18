function opensimpleconfig(router, peer) {
	window.open('simpleconfig.php?router=' + router + '&remotepeer=' + peer, peer + '_' + router, 'menubar=0,resizable=1,width=600,height=400,location=0,status=0');
	return false;
}
