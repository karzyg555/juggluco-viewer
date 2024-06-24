Juggluco viewer is simple PHP script with Javascript code to visualize glucose readings returned by Juggluco server. 
Using default settings it displays graph and table with last 55 glucose readings with 4 min 30 seconds gap between them.
Glucose readings in range (70-170 mg/dl) are presented in green, glucose readings below range are red and above range are yellow. 
Additionally on the graph you can find black line, that visualizes glucose readings from the same period yesterday.
Juggluco viewer also warns you if there is no new glucose readings available for more than 5 mintues.

You can re-configure all above settings using below script variables:
- $defaultInterval
- $defaultCount
- $defaultSyncTimeout
- $highGlucose
- $lowGlucose
