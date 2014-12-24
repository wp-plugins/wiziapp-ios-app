// Google analytics - Should *always* be in the end
var _gaq = _gaq || [];
if (typeof(_gaq.splice) == 'function'){
	_gaq.splice(0, _gaq.length);
}
_gaq.push(['_setAccount', wiziapp_name_space.analytics_account]);
_gaq.push(['_setDomainName', wiziapp_name_space.url.replace('api.', '.')]);
_gaq.push(['_setAllowLinker', true]);
_gaq.push(['_setAllowHash', false]);
_gaq.push(['_trackPageview', '/StartScanningGoal.php']);
(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();