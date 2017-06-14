# Digilent Hackster Plugin for DokuWiki

This plugin displays projects found on Hackster for a given product. For example,
to display projects related to the "Arty Board" (as it is known on Hackster) one would add

<center>{{Digilent Hackster | product = "Arty Board"

}}</center>

to the wiki page (newline needs to be present after the product value). Note
that the product name must match what is shown on Hackster in order to be recognized.
Also note that the quotations around the product name aren't necessary, but are
encouraged just for visual reasons.

## Setup

In order for the plugin to work, a client ID and client secret need to be obtained
from Hackster. To do so, follow [this](https://hacksterio.api-docs.io/2.0/getting-started/authentication) link.
Once obtained, enter your ID and secret in the config.php file, and you should
be good to go.