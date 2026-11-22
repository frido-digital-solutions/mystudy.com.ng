#!/bin/sh
#
# Where the site lives on the server.
#
# The site is an addon domain, so its files are not in $HOME/public_html/
# itself - they are in the addon's own folder inside it. This script works
# out which one that is, and prints it with a trailing slash so it can be
# used directly as a copy destination.
#
# It lives in its own file, rather than being written inline in .cpanel.yml,
# for one reason: every copy line in the deploy runs this itself. That way
# each line knows where the site is, whether or not the deploy script's
# variables carry over from one line to the next.
#
# The order matters: the addon's own folder is checked first, because that is
# the site. $HOME/public_html/ is the last resort, for a site that really is
# the main domain.

if [ -d "$HOME/public_html/newmystudy.com.ng" ]; then
    echo "$HOME/public_html/newmystudy.com.ng/"
elif [ -d "$HOME/domains/newmystudy.com.ng/public_html" ]; then
    echo "$HOME/domains/newmystudy.com.ng/public_html/"
elif [ -d "$HOME/public_html" ]; then
    echo "$HOME/public_html/"
else
    echo "$HOME/public_html/"
fi
