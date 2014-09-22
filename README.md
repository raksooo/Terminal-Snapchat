<h1>Terminal Snapchat</h1>

To log in, add auth_token from other device to auth_token.txt
On android the token can be found in /data/data/com.snapchat.android/shared_prefs/com.snapchat.android_preferences.xml. Or hardcode you password into snapchat.php.

When running there's a few different commands:<br />
logout: Logs you out and closes the application<br />
e|exit: Exits<br />
s|sent: Lists sent snaps<br />
r|received: Lists received snaps<br />
a|all: Lists both sent and received snaps<br />
f|friends: Lists friends<br />
u|update: Reloads data<br />
ls: Shows file in current directory<br />
cd: Steps between directories<br />
c|clr|clear: clears screen<br />
v|view <index>: Shows snap with specified index, index is shown when running r|received<br />
p|send <file> <to> <duration=10>: Sends a file to a username or index in friendslist. Default duration is 10s.<br />

Single command mode:<br />
It is possible to run the application with the -s flag to run it in single command mode. To specify action the -s flag should be followed by a command specified above.
