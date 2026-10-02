<div class="space-y-2" x-data="{ showOther: false, customDistrict: '' }">
<label for="district_name" class="block text-sm font-medium text-gray-700">District Name </label>
    <select id="districtName" x-model="districtName" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm transition-all focus:outline-none focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10"
            @change="showOther = districtName === 'other'; if(!showOther) customDistrict = ''"
            :name="showOther ? '' : 'districtName'">
        <option value="" selected>Select District Name</option>
        <option value="DALA">DALA</option>
        <option value="DAWAKIN KUDU">DAWAKIN KUDU</option>
        <option value="FAGGE">FAGGE</option> 
        <option value="GWALE">GWALE</option>
        <option value="KUMBOTSO">KUMBOTSO</option>
        <option value="AJINGI">AJINGI</option>
        <option value="ALBASU">ALBASU</option>
        <option value="BAGWAI">BAGWAI</option>
        <option value="BEBEJI">BEBEJI</option>
        <option value="BICHI">BICHI</option>
        <option value="BUNKURE">BUNKURE</option>
        <option value="CITY">CITY</option>
        <option value="CITY DISTRICT">CITY DISTRICT</option>
        <option value="D/KUDU">D/KUDU</option>
        <option value="DAMBATTA">DAMBATTA</option>
        <option value="DAN DINSHE KOFAR DAWANAU">DAN DINSHE KOFAR DAWANAU</option>
        <option value="DANBATTA">DANBATTA</option>
        <option value="DAWAKIL KUDU">DAWAKIL KUDU</option>
        <option value="DAWAKIN KUDU DISTRICT">DAWAKIN KUDU DISTRICT</option>
        <option value="DAWAKIN TOFA">DAWAKIN TOFA</option>
        <option value="DAWAKIN-KUDU">DAWAKIN-KUDU</option>
        <option value="DAWAKIN-TOFA">DAWAKIN-TOFA</option>
        <option value="DAWANAU TOFA">DAWANAU TOFA</option>
        <option value="DOGUWA">DOGUWA</option>
        <option value="DORAYI KARAMA">DORAYI KARAMA</option>
        <option value="GABASAWA">GABASAWA</option>
        <option value="GARKO">GARKO</option>
        <option value="GARUN MALAM">GARUN MALAM</option>
        <option value="GARUN MALLAM">GARUN MALLAM</option>
        <option value="GAYA">GAYA</option>
        <option value="GEZAWA">GEZAWA</option>
        <option value="GWALA">GWALA</option>
        <option value="GWALE DISTRICT">GWALE DISTRICT</option>
        <option value="GWAMMAJA">GWAMMAJA</option>
        <option value="GWARZO">GWARZO</option>
        <option value="HAUSAWA">HAUSAWA</option>
        <option value="INUBAWA">INUBAWA</option>
        <option value="KABO">KABO</option>
        <option value="KANO CITY">KANO CITY</option>
        <option value="KANO MUNICIPAL">KANO MUNICIPAL</option>
        <option value="KANO MUNICIPAL CITY">KANO MUNICIPAL CITY</option>
        <option value="KANO STATE">KANO STATE</option>
        <option value="KANO-CITY">KANO-CITY</option>
        <option value="KARAYE">KARAYE</option>
        <option value="KIBIYA">KIBIYA</option>
        <option value="KIMBOTSO">KIMBOTSO</option>
        <option value="KIRU">KIRU</option>
        <option value="KOFAR DAWANAU">KOFAR DAWANAU</option>
        <option value="KUMBOSTO">KUMBOSTO</option>
        <option value="KUMBOTSO VILLAGE">KUMBOTSO VILLAGE</option>
        <option value="KUMBOTSOI">KUMBOTSOI</option>
        <option value="KUNCHI">KUNCHI</option>
        <option value="KURA">KURA</option>
        <option value="MADOBI">MADOBI</option>
        <option value="MAKODA">MAKODA</option>
        <option value="MINJIBIR">MINJIBIR</option>
        <option value="MUNICIPAL">MUNICIPAL</option>
        <option value="MUNICIPAL LOCAL GOVERNMENT">MUNICIPAL LOCAL GOVERNMENT</option>
        <option value="MUNNICIPAL">MUNNICIPAL</option>
        <option value="NASARAWA">NASARAWA</option>
        <option value="NASSARAWA">NASSARAWA</option>
        <option value="RANO">RANO</option>
        <option value="RIMIN GADO">RIMIN GADO</option>
        <option value="RIMIN ZAKARA">RIMIN ZAKARA</option>
        <option value="ROGO">ROGO</option>
        <option value="SUMAILA">SUMAILA</option>
        <option value="TAKAI">TAKAI</option>
        <option value="TARAUNI">TARAUNI</option>
        <option value="TARAUNI DISTRICT">TARAUNI DISTRICT</option>
        <option value="TOFA">TOFA</option>
        <option value="TSANTAWA">TSANTAWA</option>
        <option value="TSANYAWA">TSANYAWA</option>
        <option value="TUDUN WADA">TUDUN WADA</option>
        <option value="UNGOGGO">UNGOGGO</option>
        <option value="UNGOGO">UNGOGO</option>
        <option value="WAJE">WAJE</option>
        <option value="WARAWA">WARAWA</option>
        <option value="WUDIL">WUDIL</option>
        <option value="ZAWACHIKI">ZAWACHIKI</option>
        <option value="other">Other</option>
    </select>
    <input 
        type="text" 
        id="otherDistrict" 
        x-show="showOther" 
        x-model="customDistrict" 
        name="districtName" 
        class="form-input text-sm property-input mt-2" 
        placeholder="Please specify other district  name"
        x-transition
    >
</div>