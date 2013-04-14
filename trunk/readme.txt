-- mBank API v0.5.1 --
Program jest cały czas w fazie rozwoju, dlatego wszelkie uwagi i propozycje są mile widziane.
Klasy i metody są nieco chaotycznie napisane.
Skrypt był pisany 'na szybko' - miał działać, nie miał być piękny i przejrzysty.
Prawdopodobnie wersja 1.0 będzie dopiero czytelna i odpowiednio udokumentowana.

-- Licencja --
Skrypt można rozpowszechniać i udostępniać na swoich stronach internetowych lub portalach, ale nie wolno usuwać informacji o autorze.
Aplikacja jest darmowa
Mile widziany link do mojej strony internetowej, gdzie udostępniona jest historia zmian: http://api.studio85.pl
Autorem skryptu jest Jakub Konefał <jakub.konefal@studio85.pl>

-- Informacje dodatkowe --
Przy prawidłowym uruchomieniu skryptu powinny zostać utworzone dwa pliki:
a) mbank.config.php - przechowuje identyfikator i hasło potrzebne do zalogowania się do mBanku.
Identyfikator i hasło są odpowiednio kodowane według 'własnego algorytmu' - jest to tylko potrzebne, żeby nie przechowywać loginu i hasła w czystej formie (plain-text).
Przy połączeniu do banku używane jest połączenie https, a identyfikator i hasło są dekodowane z w/w pliku.
b) mbank.lastData.php - przechowuje ostatnio pobrane listy przelewów - wykorzystywane w przypadku powiadomień o nowych przelewach.