<?php

namespace Database\Seeders\Data;

/**
 * Har bir daraja (A1..C2) uchun namunaviy italyancha material:
 * listening matni, reading matni va savollari, writing bo'shliqlari, speaking savollari, lug'at.
 * Real ishda bu kontent AI (Gemini/OpenAI) orqali generatsiya qilinadi va o'qituvchi tahrirlaydi.
 */
class ItalianContent
{
    /** @return array<string, array<string, mixed>> */
    public static function levels(): array
    {
        return [
            'A1' => [
                'topic' => 'Presentarsi e la famiglia',
                'listening' => [
                    'script' => 'Ciao! Mi chiamo Marco e ho ventidue anni. Sono di Milano, ma adesso abito a Bologna perché studio all\'università. La mia famiglia è piccola: mio padre si chiama Luca, mia madre si chiama Anna e ho una sorella, Giulia. Ogni mattina bevo un caffè al bar e poi vado a lezione in bicicletta. La sera cucino con i miei amici e guardiamo un film.',
                    'questions' => [
                        ['Quanti anni ha Marco?', 'single_choice', ['Ventidue' => true, 'Venti' => false, 'Trenta' => false]],
                        ['Dove abita Marco adesso?', 'single_choice', ['A Bologna' => true, 'A Milano' => false, 'A Roma' => false]],
                        ['Scrivi la frase che senti: "Ogni mattina bevo un caffè al bar."', 'dictation', 'Ogni mattina bevo un caffè al bar.'],
                    ],
                ],
                'reading' => [
                    'title' => 'La giornata di Sofia',
                    'passage' => 'Sofia è una studentessa italiana. Ha diciannove anni e vive a Firenze con la sua famiglia. La mattina si sveglia alle sette, fa colazione con latte e biscotti e va a scuola in autobus. A pranzo mangia un panino con gli amici. Il pomeriggio studia in biblioteca e la sera aiuta la mamma in cucina. Il sabato Sofia gioca a pallavolo e la domenica visita i nonni che abitano in campagna.',
                    'questions' => [
                        ['Sofia vive a Firenze.', 'true_false', 'true'],
                        ['Sofia va a scuola in macchina.', 'true_false', 'false'],
                        ['Sofia ha un fratello.', 'true_false', 'ng'],
                        ['Che cosa fa Sofia il sabato?', 'single_choice', ['Gioca a pallavolo' => true, 'Visita i nonni' => false, 'Studia in biblioteca' => false]],
                        ['La mattina Sofia fa colazione con latte e _____.', 'gap', 'biscotti'],
                    ],
                ],
                'writing' => [
                    'gaps' => [['Io _____ Ali e sono dell\'Uzbekistan.', 'sono'], ['Mia madre _____ una casa a Tashkent.', 'ha'], ['Noi _____ italiano a scuola.', 'studiamo']],
                    'essay' => ['Scrivi una breve presentazione di te stesso (nome, età, città, famiglia, hobby). Minimo 40 parole.', 'Mi chiamo Aziza, ho vent\'anni e vivo a Tashkent. La mia famiglia è grande: ho due fratelli e una sorella. Studio italiano perché voglio lavorare in Italia. Nel tempo libero leggo e ascolto musica.', 40],
                ],
                'speaking' => [
                    ['Come ti chiami e di dove sei?', 'Mi chiamo Ali e sono di Tashkent.'],
                    ['Quanti anni hai?', 'Ho ventidue anni.'],
                    ['Descrivi la tua famiglia.', 'La mia famiglia è grande: ho un padre, una madre, due fratelli e una sorella.'],
                ],
                'vocabulary' => [
                    'category' => 'Salomlashish va tanishuv',
                    'cards' => [
                        ['Buongiorno', 'Xayrli kun', 'Good morning', 'Добрый день', 'Buongiorno, come sta?'],
                        ['Ciao', 'Salom / Xayr', 'Hi / Bye', 'Привет / Пока', 'Ciao, Marco!'],
                        ['Grazie', 'Rahmat', 'Thank you', 'Спасибо', 'Grazie mille per l\'aiuto.'],
                        ['Per favore', 'Iltimos', 'Please', 'Пожалуйста', 'Un caffè, per favore.'],
                        ['Mi chiamo', 'Mening ismim', 'My name is', 'Меня зовут', 'Mi chiamo Sofia.'],
                        ['Arrivederci', 'Xayr (rasmiy)', 'Goodbye', 'До свидания', 'Arrivederci, signora Rossi.'],
                        ['La famiglia', 'Oila', 'Family', 'Семья', 'La mia famiglia è grande.'],
                        ['Il fratello', 'Aka / uka', 'Brother', 'Брат', 'Mio fratello ha dieci anni.'],
                        ['La sorella', 'Opa / singil', 'Sister', 'Сестра', 'Mia sorella studia medicina.'],
                        ['La casa', 'Uy', 'House', 'Дом', 'La casa è grande e luminosa.'],
                        ['Il lavoro', 'Ish', 'Work / job', 'Работа', 'Il lavoro comincia alle nove.'],
                        ['Scusi', 'Kechirasiz', 'Excuse me', 'Извините', 'Scusi, dov\'è la stazione?'],
                    ],
                ],
            ],
            'A2' => [
                'topic' => 'Viaggi e tempo libero',
                'listening' => [
                    'script' => 'Buonasera a tutti. Domani partiamo per Venezia con il treno delle otto e un quarto dalla stazione centrale. Il viaggio dura circa due ore e mezza. Portate un documento, l\'acqua e una giacca perché la sera fa fresco. Alle undici visitiamo Piazza San Marco e poi pranziamo in una trattoria vicino al Ponte di Rialto. Il ritorno è previsto per le sette di sera.',
                    'questions' => [
                        ['A che ora parte il treno?', 'single_choice', ['Alle otto e un quarto' => true, 'Alle otto e mezza' => false, 'Alle undici' => false]],
                        ['Quanto dura il viaggio?', 'single_choice', ['Circa due ore e mezza' => true, 'Un\'ora' => false, 'Quattro ore' => false]],
                        ['Scrivi la frase che senti: "Il ritorno è previsto per le sette di sera."', 'dictation', 'Il ritorno è previsto per le sette di sera.'],
                    ],
                ],
                'reading' => [
                    'title' => 'Un fine settimana a Roma',
                    'passage' => 'Lo scorso fine settimana Luca e Chiara sono andati a Roma. Sono partiti venerdì sera in autobus e sono arrivati a mezzanotte. Sabato mattina hanno visitato il Colosseo e il Foro Romano; il pomeriggio hanno fatto una passeggiata a Trastevere, dove hanno mangiato una pizza buonissima. Domenica ha piovuto, quindi hanno passato la giornata ai Musei Vaticani. Sono tornati a casa stanchi ma felici e hanno deciso di tornare a Roma in primavera.',
                    'questions' => [
                        ['Luca e Chiara sono andati a Roma in treno.', 'true_false', 'false'],
                        ['Domenica ha piovuto.', 'true_false', 'true'],
                        ['Hanno comprato dei souvenir a Trastevere.', 'true_false', 'ng'],
                        ['Che cosa hanno visitato sabato mattina?', 'single_choice', ['Il Colosseo e il Foro Romano' => true, 'I Musei Vaticani' => false, 'Trastevere' => false]],
                        ['Sono tornati a casa stanchi ma _____.', 'gap', 'felici'],
                    ],
                ],
                'writing' => [
                    'gaps' => [['Ieri _____ andato al mercato con mio padre.', 'sono'], ['L\'estate scorsa _____ visitato Samarcanda.', 'abbiamo'], ['Domani _____ al cinema con gli amici.', 'andrò']],
                    'essay' => ['Racconta il tuo ultimo viaggio: dove sei andato, con chi, che cosa hai fatto. Minimo 60 parole.', 'L\'anno scorso sono andato a Bukhara con la mia famiglia. Siamo partiti in treno la mattina presto. Abbiamo visitato la città vecchia, le madrase e il mercato. La sera abbiamo mangiato il plov in un ristorante tradizionale. È stato un viaggio bellissimo.', 60],
                ],
                'speaking' => [
                    ['Che cosa fai nel tempo libero?', 'Nel tempo libero leggo libri, ascolto musica e faccio sport con gli amici.'],
                    ['Racconta che cosa hai fatto ieri.', 'Ieri mi sono svegliato alle sette, sono andato al lavoro e la sera ho cenato con la mia famiglia.'],
                    ['Qual è il tuo posto preferito nella tua città?', 'Il mio posto preferito è il parco vicino a casa mia perché è tranquillo e verde.'],
                ],
                'vocabulary' => [
                    'category' => 'Sayohat va transport',
                    'cards' => [
                        ['Il treno', 'Poyezd', 'Train', 'Поезд', 'Il treno parte alle otto.'],
                        ['La stazione', 'Vokzal', 'Station', 'Вокзал', 'La stazione è vicina al centro.'],
                        ['Il biglietto', 'Chipta', 'Ticket', 'Билет', 'Ho comprato il biglietto online.'],
                        ['L\'aeroporto', 'Aeroport', 'Airport', 'Аэропорт', 'L\'aeroporto è lontano dalla città.'],
                        ['La valigia', 'Chamadon', 'Suitcase', 'Чемодан', 'La mia valigia è pesante.'],
                        ['Il passaporto', 'Pasport', 'Passport', 'Паспорт', 'Non dimenticare il passaporto!'],
                        ['Prenotare', 'Band qilmoq', 'To book', 'Бронировать', 'Vorrei prenotare una camera.'],
                        ['L\'albergo', 'Mehmonxona', 'Hotel', 'Гостиница', 'L\'albergo ha una bella vista.'],
                        ['Il fine settimana', 'Dam olish kunlari', 'Weekend', 'Выходные', 'Il fine settimana andiamo al mare.'],
                        ['La passeggiata', 'Sayr', 'Walk', 'Прогулка', 'Facciamo una passeggiata in centro.'],
                    ],
                ],
            ],
            'B1' => [
                'topic' => 'Lavoro e vita quotidiana',
                'listening' => [
                    'script' => 'Buongiorno, sono Elena Ricci dell\'ufficio del personale. La chiamo per il colloquio di lavoro di giovedì. L\'appuntamento è confermato per le dieci nella nostra sede di via Garibaldi, al terzo piano. Le chiedo di portare il curriculum aggiornato e una copia dei suoi certificati di lingua. Il colloquio durerà circa quaranta minuti e sarà in italiano e in inglese. Se ha bisogno di cambiare l\'orario, mi scriva un\'e-mail entro domani.',
                    'questions' => [
                        ['Quando è il colloquio?', 'single_choice', ['Giovedì alle dieci' => true, 'Venerdì alle dieci' => false, 'Giovedì alle nove' => false]],
                        ['Che cosa deve portare il candidato?', 'multiple_choice', ['Il curriculum aggiornato' => true, 'Una copia dei certificati di lingua' => true, 'Una fotografia' => false, 'Il passaporto' => false]],
                        ['Scrivi la frase che senti: "Il colloquio durerà circa quaranta minuti."', 'dictation', 'Il colloquio durerà circa quaranta minuti.'],
                    ],
                ],
                'reading' => [
                    'title' => 'Lavorare da casa',
                    'passage' => 'Negli ultimi anni sempre più aziende italiane permettono ai dipendenti di lavorare da casa almeno due giorni alla settimana. Secondo un\'indagine recente, il 68% dei lavoratori si dichiara più soddisfatto e il 54% afferma di essere più produttivo. Tuttavia non mancano le difficoltà: molti lamentano la mancanza di contatto con i colleghi e la difficoltà di separare il tempo del lavoro da quello della famiglia. Gli esperti consigliano di creare uno spazio dedicato al lavoro, di rispettare orari fissi e di fare pause regolari. Il lavoro ibrido, che unisce giorni in ufficio e giorni a casa, sembra essere la soluzione preferita dalla maggior parte delle persone.',
                    'questions' => [
                        ['La maggioranza dei lavoratori intervistati è più soddisfatta lavorando da casa.', 'true_false', 'true'],
                        ['Tutti i lavoratori trovano facile separare lavoro e famiglia.', 'true_false', 'false'],
                        ['Le aziende pagano un bonus a chi lavora da casa.', 'true_false', 'ng'],
                        ['Qual è la soluzione preferita dalla maggior parte delle persone?', 'single_choice', ['Il lavoro ibrido' => true, 'Lavorare sempre in ufficio' => false, 'Lavorare sempre da casa' => false]],
                        ['Gli esperti consigliano di fare _____ regolari.', 'gap', 'pause'],
                    ],
                ],
                'writing' => [
                    'gaps' => [['Se avessi più tempo, _____ un corso di cucina.', 'farei'], ['Penso che questo lavoro _____ interessante.', 'sia'], ['Quando ero piccolo, _____ sempre al parco.', 'giocavo']],
                    'essay' => ['Scrivi un\'e-mail formale a un\'azienda per candidarti a un posto di lavoro. Presenta la tua esperienza e spiega perché sei adatto. Minimo 100 parole.', 'Gentile Responsabile, mi chiamo Dilshod Karimov e le scrivo per candidarmi alla posizione di assistente commerciale pubblicata sul vostro sito. Ho una laurea in economia e due anni di esperienza in un\'azienda di import-export, dove mi occupavo dei contatti con i fornitori italiani. Parlo italiano a livello B1 e inglese a livello B2. Sono una persona precisa, motivata e abituata a lavorare in squadra. Allego il mio curriculum e resto a disposizione per un colloquio. Cordiali saluti, Dilshod Karimov', 100],
                ],
                'speaking' => [
                    ['Descrivi il tuo lavoro ideale e spiega perché.', 'Il mio lavoro ideale sarebbe fare l\'interprete, perché mi piacciono le lingue e viaggiare.'],
                    ['Quali sono i vantaggi e gli svantaggi di lavorare da casa?', 'Lavorare da casa fa risparmiare tempo, ma è difficile separare il lavoro dalla vita privata.'],
                    ['Racconta un\'esperienza di lavoro o di studio importante per te.', 'Due anni fa ho fatto uno stage in un\'agenzia di viaggi e ho imparato a parlare con i clienti stranieri.'],
                ],
                'vocabulary' => [
                    'category' => 'Ish va kasblar',
                    'cards' => [
                        ['Il colloquio', 'Suhbat (ish)', 'Interview', 'Собеседование', 'Ho un colloquio di lavoro giovedì.'],
                        ['Il curriculum', 'Rezyume', 'CV / résumé', 'Резюме', 'Invia il curriculum aggiornato.'],
                        ['L\'azienda', 'Korxona', 'Company', 'Компания', 'L\'azienda cerca un nuovo impiegato.'],
                        ['Lo stipendio', 'Maosh', 'Salary', 'Зарплата', 'Lo stipendio arriva a fine mese.'],
                        ['Il dipendente', 'Xodim', 'Employee', 'Сотрудник', 'I dipendenti lavorano otto ore.'],
                        ['Assumere', 'Ishga olmoq', 'To hire', 'Нанимать', 'Vogliono assumere tre persone.'],
                        ['L\'orario', 'Ish vaqti / jadval', 'Schedule / hours', 'Расписание', 'L\'orario è dalle nove alle diciotto.'],
                        ['La riunione', 'Yig\'ilish', 'Meeting', 'Собрание', 'La riunione è rimandata a domani.'],
                        ['L\'esperienza', 'Tajriba', 'Experience', 'Опыт', 'Ho esperienza nel settore turistico.'],
                        ['Il contratto', 'Shartnoma', 'Contract', 'Контракт', 'Ho firmato un contratto di un anno.'],
                    ],
                ],
            ],
            'B2' => [
                'topic' => 'Ambiente e società',
                'listening' => [
                    'script' => 'Nel nostro programma di oggi parliamo di mobilità sostenibile. Il comune di Torino ha annunciato un piano per raddoppiare le piste ciclabili entro il 2028 e per introdurre cinquanta nuovi autobus elettrici. Secondo l\'assessore all\'ambiente, l\'obiettivo è ridurre del venti per cento le emissioni del traffico urbano. Non tutti però sono d\'accordo: le associazioni dei commercianti temono che la chiusura di alcune strade al traffico riduca i clienti. Il dibattito pubblico continuerà nelle prossime settimane con tre incontri aperti ai cittadini.',
                    'questions' => [
                        ['Qual è l\'obiettivo del piano di Torino?', 'single_choice', ['Ridurre del 20% le emissioni del traffico' => true, 'Costruire una nuova metropolitana' => false, 'Aumentare i parcheggi' => false]],
                        ['Perché i commercianti sono preoccupati?', 'single_choice', ['Temono di perdere clienti' => true, 'Vogliono più autobus' => false, 'Non amano le biciclette' => false]],
                        ['Scrivi la frase che senti: "Il dibattito pubblico continuerà nelle prossime settimane."', 'dictation', 'Il dibattito pubblico continuerà nelle prossime settimane.'],
                    ],
                ],
                'reading' => [
                    'title' => 'La plastica nei mari italiani',
                    'passage' => 'Ogni anno nel Mediterraneo finiscono circa 230 mila tonnellate di plastica, e l\'Italia è tra i paesi che ne producono di più. Uno studio dell\'Università di Genova ha rilevato microplastiche nel 90% dei campioni di acqua analizzati lungo la costa ligure. Le conseguenze riguardano non solo la fauna marina, ma anche la salute umana, poiché le microplastiche entrano nella catena alimentare attraverso il pesce. Negli ultimi anni sono state introdotte misure importanti: il divieto delle buste di plastica non biodegradabili, la tassa sulle bottiglie monouso e campagne di pulizia delle spiagge che coinvolgono migliaia di volontari. Tuttavia gli esperti sostengono che, senza un cambiamento reale delle abitudini dei consumatori, queste misure non saranno sufficienti.',
                    'questions' => [
                        ['Le microplastiche sono state trovate nella maggior parte dei campioni analizzati.', 'true_false', 'true'],
                        ['Secondo gli esperti, le misure attuali sono sufficienti.', 'true_false', 'false'],
                        ['Il governo italiano ha vietato la pesca nel Mediterraneo.', 'true_false', 'ng'],
                        ['In che modo le microplastiche arrivano all\'uomo?', 'single_choice', ['Attraverso la catena alimentare' => true, 'Attraverso l\'aria' => false, 'Attraverso l\'acqua del rubinetto' => false]],
                        ['Le campagne di pulizia coinvolgono migliaia di _____.', 'gap', 'volontari'],
                    ],
                ],
                'writing' => [
                    'gaps' => [['Benché _____ tardi, siamo usciti lo stesso.', 'fosse'], ['Se aveste studiato di più, _____ superato l\'esame.', 'avreste'], ['Il documento è stato _____ dal direttore.', 'firmato']],
                    'essay' => ['"Le città dovrebbero vietare le automobili nel centro storico." Esprimi la tua opinione con argomenti a favore e contro. Minimo 150 parole.', 'Il tema della chiusura dei centri storici al traffico è molto discusso. Da un lato, vietare le automobili ridurrebbe l\'inquinamento, il rumore e renderebbe le strade più sicure per pedoni e ciclisti; molte città europee che hanno adottato questa misura hanno visto crescere il turismo e il commercio locale. Dall\'altro lato, i residenti e i commercianti temono difficoltà pratiche: consegne, accesso per gli anziani e i disabili, perdita di clienti. A mio parere la soluzione migliore è un divieto graduale, accompagnato da trasporti pubblici efficienti, parcheggi esterni e permessi speciali per chi ne ha bisogno. In questo modo si potrebbero conciliare la qualità dell\'aria e le esigenze della comunità.', 150],
                ],
                'speaking' => [
                    ['Quali misure dovrebbe adottare il tuo paese per proteggere l\'ambiente?', 'Il mio paese dovrebbe investire nelle energie rinnovabili, migliorare la raccolta differenziata e limitare l\'uso della plastica.'],
                    ['Sei d\'accordo con il divieto delle auto nei centri storici? Argomenta.', 'Sono d\'accordo in parte: il divieto migliora la qualità dell\'aria, ma servono alternative per chi lavora in centro.'],
                    ['Descrivi un cambiamento sociale importante degli ultimi dieci anni.', 'Negli ultimi dieci anni lo smart working ha cambiato il modo di lavorare e di vivere le città.'],
                ],
                'vocabulary' => [
                    'category' => 'Atrof-muhit va jamiyat',
                    'cards' => [
                        ['L\'inquinamento', 'Ifloslanish', 'Pollution', 'Загрязнение', 'L\'inquinamento dell\'aria è aumentato.'],
                        ['Sostenibile', 'Barqaror', 'Sustainable', 'Устойчивый', 'Serve una mobilità sostenibile.'],
                        ['La raccolta differenziata', 'Chiqindilarni saralash', 'Recycling collection', 'Раздельный сбор', 'La raccolta differenziata è obbligatoria.'],
                        ['Il riscaldamento globale', 'Global isish', 'Global warming', 'Глобальное потепление', 'Il riscaldamento globale minaccia i ghiacciai.'],
                        ['Le energie rinnovabili', 'Qayta tiklanuvchi energiya', 'Renewable energy', 'Возобновляемая энергия', 'Investire nelle energie rinnovabili.'],
                        ['Il divieto', 'Taqiq', 'Ban', 'Запрет', 'Il divieto entra in vigore domani.'],
                        ['La misura', 'Chora', 'Measure', 'Мера', 'Il governo ha adottato nuove misure.'],
                        ['Il cittadino', 'Fuqaro', 'Citizen', 'Гражданин', 'Ogni cittadino ha diritto di voto.'],
                        ['Coinvolgere', 'Jalb qilmoq', 'To involve', 'Вовлекать', 'La campagna coinvolge i giovani.'],
                        ['Il consumatore', 'Iste\'molchi', 'Consumer', 'Потребитель', 'I consumatori chiedono prodotti ecologici.'],
                    ],
                ],
            ],
            'C1' => [
                'topic' => 'Cultura, media e tecnologia',
                'listening' => [
                    'script' => 'Il rapporto annuale sull\'informazione in Italia rivela un dato sorprendente: per la prima volta i podcast superano la radio tradizionale come fonte di approfondimento tra i giovani sotto i trent\'anni. Gli intervistati apprezzano soprattutto la possibilità di scegliere il momento dell\'ascolto e la profondità dei contenuti, spesso assente nei notiziari televisivi. Tuttavia gli autori dello studio mettono in guardia contro il rischio di una frammentazione dell\'opinione pubblica, poiché ciascuno tende a selezionare solo le voci che confermano le proprie convinzioni. La sfida per gli editori, concludono, sarà coniugare la personalizzazione con la pluralità delle fonti.',
                    'questions' => [
                        ['Quale fonte di informazione superano i podcast tra i giovani?', 'single_choice', ['La radio tradizionale' => true, 'I quotidiani cartacei' => false, 'I social network' => false]],
                        ['Quale rischio segnalano gli autori dello studio?', 'single_choice', ['La frammentazione dell\'opinione pubblica' => true, 'La scomparsa della televisione' => false, 'L\'aumento dei costi' => false]],
                        ['Scrivi la frase che senti: "La sfida per gli editori sarà coniugare la personalizzazione con la pluralità delle fonti."', 'dictation', 'La sfida per gli editori sarà coniugare la personalizzazione con la pluralità delle fonti.'],
                    ],
                ],
                'reading' => [
                    'title' => 'L\'intelligenza artificiale nelle redazioni',
                    'passage' => 'L\'ingresso dell\'intelligenza artificiale nelle redazioni giornalistiche italiane sta ridefinendo il mestiere del cronista. Alcune testate utilizzano già sistemi automatici per redigere brevi notizie finanziarie o sportive, liberando i giornalisti dai compiti più ripetitivi. I sostenitori di questa trasformazione sottolineano il guadagno in rapidità e la possibilità di dedicare più risorse alle inchieste. I critici, al contrario, temono un impoverimento della qualità e una progressiva perdita di posti di lavoro, oltre al pericolo che errori generati dagli algoritmi vengano pubblicati senza un adeguato controllo umano. L\'Ordine dei giornalisti ha pertanto proposto un codice di condotta che impone la trasparenza sull\'uso di strumenti automatici e la verifica obbligatoria da parte di un redattore. Resta aperta la questione della responsabilità: chi risponde di un errore commesso da una macchina?',
                    'questions' => [
                        ['Alcune testate italiane usano già l\'IA per scrivere notizie brevi.', 'true_false', 'true'],
                        ['I critici ritengono che l\'IA migliori la qualità dell\'informazione.', 'true_false', 'false'],
                        ['Il codice di condotta è già stato approvato dal Parlamento.', 'true_false', 'ng'],
                        ['Che cosa impone il codice di condotta proposto?', 'single_choice', ['Trasparenza e verifica umana obbligatoria' => true, 'Il divieto totale dell\'IA' => false, 'La riduzione degli stipendi' => false]],
                        ['Resta aperta la questione della _____.', 'gap', 'responsabilità'],
                    ],
                ],
                'writing' => [
                    'gaps' => [['Qualora la proposta _____ approvata, entrerà in vigore a gennaio.', 'venisse'], ['Nonostante _____ avvertito in tempo, non ha cambiato idea.', 'fosse stato'], ['Le sue dichiarazioni sono state _____ dai fatti.', 'smentite']],
                    'essay' => ['Scrivi un articolo di opinione (200–250 parole) sul ruolo dei social media nella formazione dell\'opinione pubblica, con una tesi chiara e argomenti strutturati.', 'I social media hanno trasformato radicalmente il modo in cui l\'opinione pubblica si forma e si esprime. Se da un lato hanno democratizzato l\'accesso all\'informazione, permettendo a chiunque di far sentire la propria voce, dall\'altro hanno favorito la diffusione di notizie false e la polarizzazione del dibattito. Gli algoritmi, progettati per massimizzare l\'attenzione, tendono a mostrarci contenuti che confermano le nostre convinzioni, chiudendoci in vere e proprie bolle informative. Ne consegue che il confronto tra posizioni diverse diventa sempre più raro. A mio avviso, la risposta non può essere la censura, bensì l\'educazione ai media fin dalla scuola e una maggiore responsabilità delle piattaforme nella trasparenza dei propri meccanismi. Solo cittadini consapevoli possono trasformare i social da strumenti di manipolazione in spazi di dialogo autentico.', 200],
                ],
                'speaking' => [
                    ['In che misura la tecnologia ha cambiato il modo in cui ci informiamo? Argomenta con esempi.', 'La tecnologia ha reso l\'informazione immediata e personalizzata, ma ha anche ridotto la verifica delle fonti; basti pensare alla diffusione delle fake news durante le elezioni.'],
                    ['Discuti i pro e i contro dell\'uso dell\'intelligenza artificiale nel giornalismo.', 'L\'IA velocizza la produzione di notizie di routine, tuttavia rischia di ridurre la qualità e solleva problemi di responsabilità.'],
                    ['Quale ruolo dovrebbe avere lo Stato nella regolamentazione dei media digitali?', 'Lo Stato dovrebbe garantire la trasparenza degli algoritmi e tutelare il pluralismo, senza però limitare la libertà di espressione.'],
                ],
                'vocabulary' => [
                    'category' => 'Media va texnologiya',
                    'cards' => [
                        ['La testata', 'Nashr (gazeta)', 'Newspaper / outlet', 'Издание', 'Una testata nazionale ha pubblicato l\'inchiesta.'],
                        ['L\'inchiesta', 'Tergov / tadqiqot (jurnalistik)', 'Investigation', 'Расследование', 'L\'inchiesta ha rivelato nuovi dettagli.'],
                        ['La redazione', 'Tahririyat', 'Editorial office', 'Редакция', 'La redazione lavora fino a tardi.'],
                        ['Attendibile', 'Ishonchli', 'Reliable', 'Достоверный', 'Cerca fonti attendibili.'],
                        ['Smentire', 'Rad etmoq', 'To deny / refute', 'Опровергать', 'Il ministro ha smentito la notizia.'],
                        ['La polarizzazione', 'Qutblanish', 'Polarisation', 'Поляризация', 'La polarizzazione del dibattito cresce.'],
                        ['Coniugare', 'Uyg\'unlashtirmoq', 'To combine', 'Сочетать', 'Coniugare rapidità e qualità.'],
                        ['La trasparenza', 'Shaffoflik', 'Transparency', 'Прозрачность', 'La trasparenza è fondamentale.'],
                        ['Approfondire', 'Chuqurlashtirmoq', 'To deepen', 'Углублять', 'Vorrei approfondire l\'argomento.'],
                        ['Mettere in guardia', 'Ogohlantirmoq', 'To warn', 'Предостерегать', 'Gli esperti mettono in guardia dai rischi.'],
                    ],
                ],
            ],
            'C2' => [
                'topic' => 'Letteratura, storia e linguaggio',
                'listening' => [
                    'script' => 'Nella conferenza di oggi vorrei soffermarmi su un aspetto spesso trascurato della Divina Commedia: la sua straordinaria capacità di fondere il registro sublime con quello più crudamente realistico. Dante non esita ad accostare il lessico teologico alle espressioni del volgo fiorentino, creando un impasto linguistico che i suoi contemporanei giudicarono audace, quando non addirittura sconveniente. È proprio questa tensione, a mio giudizio, a garantire al poema la sua perenne attualità: ogni generazione vi riconosce la propria lingua, le proprie contraddizioni, la propria umanità. Non a caso, Primo Levi ricorderà a memoria il canto di Ulisse nel lager, come estremo baluardo di dignità.',
                    'questions' => [
                        ['Su quale aspetto della Divina Commedia si sofferma il relatore?', 'single_choice', ['La fusione tra registro sublime e realistico' => true, 'La struttura in tre cantiche' => false, 'La biografia di Dante' => false]],
                        ['Come giudicarono i contemporanei le scelte linguistiche di Dante?', 'single_choice', ['Audaci, talvolta sconvenienti' => true, 'Troppo semplici' => false, 'Indifferenti' => false]],
                        ['Scrivi la frase che senti: "Ogni generazione vi riconosce la propria lingua, le proprie contraddizioni, la propria umanità."', 'dictation', 'Ogni generazione vi riconosce la propria lingua, le proprie contraddizioni, la propria umanità.'],
                    ],
                ],
                'reading' => [
                    'title' => 'Il dialetto come patrimonio',
                    'passage' => 'Per decenni i dialetti italiani sono stati considerati un ostacolo all\'unificazione linguistica del Paese, tanto che la scuola dell\'immediato dopoguerra ne scoraggiava apertamente l\'uso. Oggi il quadro appare rovesciato: linguisti, amministrazioni locali e persino l\'UNESCO li riconoscono come patrimonio immateriale da tutelare. La ragione di questo mutamento è duplice. Da un lato, l\'italiano standard si è ormai affermato in ogni strato sociale, e il dialetto non rappresenta più una minaccia alla comprensione reciproca; dall\'altro, la globalizzazione ha risvegliato un bisogno di radici e di identità locale che il dialetto incarna con immediatezza. Non mancano tuttavia le voci critiche, secondo cui una tutela istituzionale rischierebbe di trasformare una lingua viva in un reperto da museo, privandola di quella spontaneità che ne costituisce l\'essenza. Il dibattito, lungi dall\'essere concluso, interroga il rapporto stesso tra memoria e modernità.',
                    'questions' => [
                        ['Nel dopoguerra la scuola incoraggiava l\'uso del dialetto.', 'true_false', 'false'],
                        ['Oggi il dialetto non è più percepito come una minaccia alla comprensione reciproca.', 'true_false', 'true'],
                        ['La maggioranza degli italiani parla dialetto ogni giorno.', 'true_false', 'ng'],
                        ['Qual è la preoccupazione delle voci critiche?', 'single_choice', ['Che la tutela istituzionale renda il dialetto un reperto da museo' => true, 'Che il dialetto sostituisca l\'italiano' => false, 'Che l\'UNESCO imponga nuove regole' => false]],
                        ['Il dibattito interroga il rapporto tra memoria e _____.', 'gap', 'modernità'],
                    ],
                ],
                'writing' => [
                    'gaps' => [['_____ che fosse innocente, il giudice lo assolse.', 'Convinto'], ['Non v\'è dubbio che la questione _____ un\'analisi più approfondita.', 'meriti'], ['L\'opera, _____ pubblicata postuma, ebbe un successo immediato.', 'benché']],
                    'essay' => ['Scrivi un saggio breve (250–300 parole) sul tema: "La lingua è lo specchio di una società". Sviluppa una tesi originale con riferimenti culturali o letterari.', 'Sostenere che la lingua sia lo specchio di una società significa riconoscere che ogni mutamento lessicale o sintattico riflette un cambiamento nei rapporti umani, nei valori e nelle gerarchie. Basti pensare all\'evoluzione dei pronomi di cortesia in italiano: il passaggio dal "voi" al "lei" e, più recentemente, la diffusione del "tu" anche in contesti formali raccontano una società che ha progressivamente allentato le distanze sociali. Allo stesso modo, l\'ingresso massiccio di anglicismi nel linguaggio economico e tecnologico testimonia la subalternità culturale di un Paese che fatica a nominare con parole proprie le innovazioni che importa. Tuttavia lo specchio non è mai neutro: la lingua non si limita a riflettere, ma contribuisce a plasmare la realtà. Le battaglie sul linguaggio inclusivo dimostrano che modificare le parole è un modo per rinegoziare il posto di ciascuno nella comunità. Manzoni, "risciacquando i panni in Arno", scelse consapevolmente il fiorentino colto per dare voce a un\'Italia ancora da costruire. In conclusione, se la lingua è uno specchio, è uno specchio che restituisce non solo ciò che siamo, ma anche ciò che aspiriamo a diventare.', 250],
                ],
                'speaking' => [
                    ['Commenta l\'affermazione: "Tradurre è tradire". Sei d\'accordo?', 'In parte sì: ogni traduzione comporta una perdita, ma è anche un atto creativo che consente all\'opera di vivere in un\'altra cultura.'],
                    ['Quale opera della letteratura italiana consiglieresti a uno straniero e perché?', 'Consiglierei "Il Gattopardo" di Tomasi di Lampedusa, perché racconta con ironia il rapporto tra cambiamento e conservazione nella società italiana.'],
                    ['In che modo i dialetti arricchiscono o ostacolano l\'identità nazionale?', 'I dialetti arricchiscono l\'identità nazionale come patrimonio culturale, purché non diventino strumento di chiusura verso l\'esterno.'],
                ],
                'vocabulary' => [
                    'category' => 'Adabiyot va til',
                    'cards' => [
                        ['Il registro', 'Uslub (til registri)', 'Register (style)', 'Регистр', 'Il registro del testo è molto elevato.'],
                        ['Il lessico', 'Leksika', 'Vocabulary / lexicon', 'Лексика', 'Il lessico dantesco è ricchissimo.'],
                        ['Sublime', 'Yuksak, oliy', 'Sublime', 'Возвышенный', 'Uno stile sublime.'],
                        ['Il patrimonio', 'Meros', 'Heritage', 'Наследие', 'Il dialetto è un patrimonio da tutelare.'],
                        ['Il baluardo', 'Tayanch, qal\'a', 'Bulwark', 'Оплот', 'La poesia come baluardo di dignità.'],
                        ['Plasmare', 'Shakllantirmoq', 'To shape', 'Формировать', 'La lingua plasma il pensiero.'],
                        ['Lungi da', 'Uzoq (…dan)', 'Far from', 'Далеко не', 'Lungi dall\'essere concluso, il dibattito continua.'],
                        ['Postumo', 'Vafotidan keyingi', 'Posthumous', 'Посмертный', 'Un\'opera pubblicata postuma.'],
                        ['La subalternità', 'Tobelik', 'Subordination', 'Подчинённость', 'Una subalternità culturale.'],
                        ['Rinegoziare', 'Qayta kelishmoq', 'To renegotiate', 'Пересматривать', 'Rinegoziare i ruoli nella società.'],
                    ],
                ],
            ],
        ];
    }

    /** Yo'nalishlar bo'yicha qo'shimcha lug'at (tibbiyot / fermer / maxsus) */
    public static function trackVocabulary(): array
    {
        return [
            'medical' => ['level' => 'B1', 'title' => 'Tibbiy atamalar', 'category' => 'Tibbiyot', 'cards' => [
                ['Il paziente', 'Bemor', 'Patient', 'Пациент', 'Il paziente ha la febbre.'],
                ['La febbre', 'Isitma', 'Fever', 'Температура', 'Ho la febbre da due giorni.'],
                ['La ricetta', 'Retsept', 'Prescription', 'Рецепт', 'Il medico scrive la ricetta.'],
                ['Il pronto soccorso', 'Tez yordam bo\'limi', 'Emergency room', 'Скорая помощь', 'Andiamo al pronto soccorso.'],
                ['L\'infermiere', 'Hamshira', 'Nurse', 'Медбрат / медсестра', 'L\'infermiere misura la pressione.'],
                ['La pressione', 'Qon bosimi', 'Blood pressure', 'Давление', 'La pressione è alta.'],
                ['Il dolore', 'Og\'riq', 'Pain', 'Боль', 'Dove sente il dolore?'],
                ['La terapia', 'Davolash', 'Therapy / treatment', 'Терапия', 'La terapia dura dieci giorni.'],
                ['Il reparto', 'Bo\'lim (kasalxona)', 'Ward', 'Отделение', 'Il reparto di cardiologia.'],
                ['Allergico', 'Allergiyasi bor', 'Allergic', 'Аллергик', 'È allergico alla penicillina.'],
            ]],
            'farming' => ['level' => 'A2', 'title' => 'Fermer xo\'jaligi', 'category' => 'Qishloq xo\'jaligi', 'cards' => [
                ['Il contadino', 'Dehqon', 'Farmer', 'Крестьянин', 'Il contadino lavora nei campi.'],
                ['Il raccolto', 'Hosil', 'Harvest', 'Урожай', 'Il raccolto è stato buono.'],
                ['Il trattore', 'Traktor', 'Tractor', 'Трактор', 'Il trattore è nuovo.'],
                ['Seminare', 'Ekmoq', 'To sow', 'Сеять', 'Seminiamo il grano in autunno.'],
                ['L\'irrigazione', 'Sug\'orish', 'Irrigation', 'Орошение', 'L\'irrigazione a goccia risparmia acqua.'],
                ['La serra', 'Issiqxona', 'Greenhouse', 'Теплица', 'I pomodori crescono nella serra.'],
                ['Il fertilizzante', 'O\'g\'it', 'Fertilizer', 'Удобрение', 'Usiamo un fertilizzante naturale.'],
                ['La vendemmia', 'Uzum terimi', 'Grape harvest', 'Сбор винограда', 'La vendemmia è a settembre.'],
                ['L\'uliveto', 'Zaytunzor', 'Olive grove', 'Оливковая роща', 'L\'uliveto ha cento anni.'],
                ['Il bestiame', 'Chorva', 'Livestock', 'Скот', 'Il bestiame pascola in montagna.'],
            ]],
            'special' => ['level' => 'B2', 'title' => 'Viza, grant va biznes', 'category' => 'Rasmiy muloqot', 'cards' => [
                ['Il permesso di soggiorno', 'Yashash ruxsatnomasi', 'Residence permit', 'Вид на жительство', 'Ho richiesto il permesso di soggiorno.'],
                ['La borsa di studio', 'Grant / stipendiya', 'Scholarship', 'Стипендия', 'Ha vinto una borsa di studio.'],
                ['L\'ambasciata', 'Elchixona', 'Embassy', 'Посольство', 'Il colloquio è in ambasciata.'],
                ['La domanda', 'Ariza', 'Application', 'Заявление', 'Ho presentato la domanda online.'],
                ['Il modulo', 'Blanka / forma', 'Form', 'Бланк', 'Compili il modulo in stampatello.'],
                ['La scadenza', 'Muddat', 'Deadline', 'Срок', 'La scadenza è il 30 giugno.'],
                ['L\'iscrizione', 'Ro\'yxatdan o\'tish', 'Enrolment', 'Запись / зачисление', 'L\'iscrizione all\'università.'],
                ['La fattura', 'Hisob-faktura', 'Invoice', 'Счёт-фактура', 'Emettere una fattura.'],
                ['Il fornitore', 'Yetkazib beruvchi', 'Supplier', 'Поставщик', 'Il fornitore italiano consegna a maggio.'],
                ['La trattativa', 'Muzokara', 'Negotiation', 'Переговоры', 'La trattativa è andata bene.'],
            ]],
        ];
    }
}
