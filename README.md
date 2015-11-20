# ac-fusjonator-api

Dette APIet er registrert i UNINETT Connect tjenesteplattform og benyttes av tjeneste "ConnectFusjonator".

APIet mottar og prosesserer henvendelser fra en `klient` (eks. https://github.com/skrodal/ac-fusjonator-client) registrert i UNINETT Connect tjenesteplattform som har tilgang til dette APIet.
 
Dataflyt mellom `klient`<->`API`<->`AdobeConnect` er som følger:


1. `Klient` sender brukerliste til API med følgende CSV format (`current_login, new_login`):

```
    GAMMELT@HIN.NO, NYTT@UIT.no 
    karius@hin.no, karius@uit.no 
    baktus@hin.no, baktus@uit.no 
    kasper@hin.no, kasper@uit.no 
    jesper@hin.no, jesper@uit.no
```
 
2. API kryssjekker hver linje i brukerlista med Adobe Connect som følger:

- Eksisterer konto med brukernavn?
    - NEI: skip til neste linje (vi trenger ikke gjøre noe som helst med dette brukernavnet)
    - JA: bruker har konto. Sjekk for sikkerhets skyld om NYTT_brukernavn også eksisterer:
        - NEI: bra, legg til brukernavn (gammelt og nytt) i liste over kontoer som kan og skal fusjoneres
        - JA: ops! Nytt brukernavn eksisterer allerede - altså kan ikke gammel og ny konto migreres... 

3. API sender tilbake til klienten ei liste med brukerkontoer som kan migreres, og som kan kontrolleres av brukeren. I tillegg synliggjøres liste over problematiske kontoer (der begge brukernavn allerede eksisterer). Se eksempel lenger ned.

4. Klient sender så nytt kall til API med den nye lista over kontoer som kan migreres.

5. API sender hver og en bruker til Adobe Connect for å bytte fra gammelt til nytt brukernavn.

6. Når #5 er ferdig sender API svar til klient med status (liste over alle brukernavn som ble migrert).

7. Ferdig!


### Eksempelsvar fra `API` til `klient`:

Eksempel med tulle-data for å illustrere første oppslag i API: 

- Karius og Baktus har ingen konto fra før, så vi kan regne med at disse to blir ignorert. 
- Simon og Renlin har konto og vi kan forvente at ny uit adresse er ledig.
- Siste linje vet vi kommer til å skjære seg.

```
     karius@hin.no, karius@uit.no
     baktus@hin.no, baktus@uit.no
     simon@uninett.no, simon@uit.no
     renlin@uninett.no, renlin@uit.no
     simon@uninett.no, renlin@uninett.no
```

Svar fra API etter å ha sjekket med Adobe Connect:

- Første to linjer ble ignorert (de har ikke konto)
- Neste to linjer er OK - begge brukere har konto og nytt brukernavn er ikke tatt i bruk enda. 
- Siste linje: kollisjon siden nytt brukernavn allerede er tatt i bruk!

Kun brukere i "ready"-segmentet vil bli sent til Adobe Connect i neste kall for migrering.

```
{
  "ready": {
    "simon@uninett.no": {
      "message": "Klar for fusjonering til nytt brukernavn!",
      "account_info_current": {
        "id": "839338",
        "username": "simon@uninett.no"
      },
      "account_info_new": "simon@uit.no"
    },
    "renlin@uninett.no": {
      "message": "Klar for fusjonering til nytt brukernavn!",
      "account_info_current": {
        "id": "8902772",
        "username": "renlin@uninett.no"
      },
      "account_info_new": "renlin@uit.no"
    }
  },
  
  "problem": {
    "simon@uninett.no": {
      "message": "Nytt brukernavn er allerede blitt tatt i bruk!",
      "account_info_current": {
        "id": "839338",
        "username": "simon@uninett.no"
      },
      "account_info_new": {
        "id": "8902772",
        "username": "renlin@uninett.no"
      }
    }
  }
}
```


### Relatert

For mer informasjon om APIets funksjon, se https://github.com/skrodal/ac-fusjonator-client. 

